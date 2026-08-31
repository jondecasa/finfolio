<?php

namespace App\Services;

use App\Models\Holding;
use App\Models\Plan;
use App\Models\PlanRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies scheduled {@see Plan} movements to their holdings.
 *
 *  - `quantity` plans buy/sell units (recomputing the weighted average cost on
 *    buys) using the asset's current live price;
 *  - `debt` / `value` plans move a cash amount on a manually-valued holding
 *    (e.g. paying down a mortgage).
 *
 * A quantity or value can never go below zero: such a movement is recorded as
 * `skipped`. Debt is clamped at zero instead. Missed periods are not replayed —
 * a due plan runs once and its `next_run_on` rolls forward past today.
 */
class PlanRunner
{
    public function __construct(
        protected FxService $fx,
        protected PortfolioService $portfolio,
        protected PriceService $prices,
    ) {}

    /**
     * Run every plan that is due on $on.
     *
     * @return array{applied:int, skipped:int}
     */
    public function runDue(CarbonImmutable $on): array
    {
        $plans = Plan::query()->due($on)->with(['holding.asset', 'holding.account'])->get();

        return $this->runMany($plans, $on);
    }

    /**
     * Run the due plans that belong to one user.
     *
     * @return array{applied:int, skipped:int}
     */
    public function runDueForUser(User $user, CarbonImmutable $on): array
    {
        $plans = Plan::query()->due($on)
            ->whereHas('holding.account', fn ($q) => $q->where('user_id', $user->id))
            ->with(['holding.asset', 'holding.account'])
            ->get();

        return $this->runMany($plans, $on);
    }

    /**
     * @param  Collection<int, Plan>  $plans
     * @return array{applied:int, skipped:int}
     */
    protected function runMany($plans, CarbonImmutable $on): array
    {
        $applied = 0;
        $skipped = 0;
        $userIds = [];

        foreach ($plans as $plan) {
            $run = DB::transaction(fn () => $this->execute($plan, $on));
            $run->wasApplied() ? $applied++ : $skipped++;
            $userIds[$plan->holding->account->user_id] = true;
        }

        foreach (User::query()->whereIn('id', array_keys($userIds))->get() as $user) {
            $this->portfolio->snapshot($user);
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /**
     * Apply a single plan to its holding and advance its schedule. Always
     * returns a PlanRun (status `applied` or `skipped`).
     */
    public function execute(Plan $plan, CarbonImmutable $on): PlanRun
    {
        $holding = $plan->holding;

        $run = match ($plan->target) {
            'quantity' => $this->applyQuantity($plan, $holding, $on),
            'debt' => $this->applyDebt($plan, $holding, $on),
            default => $this->applyValue($plan, $holding, $on),
        };

        $plan->last_run_on = $on;
        $plan->next_run_on = $plan->rollForwardAfter($on);
        if ($plan->ends_on && $plan->next_run_on->gt($plan->ends_on)) {
            $plan->active = false;
        }
        $plan->save();

        return $run;
    }

    protected function applyQuantity(Plan $plan, Holding $holding, CarbonImmutable $on): PlanRun
    {
        $asset = $holding->asset;
        $assetCcy = $asset->currency ?: 'USD';

        // Make sure we have a fresh-ish price to buy/sell at.
        if ($asset->current_price === null || (float) $asset->current_price <= 0 || $asset->isPriceStale()) {
            $this->prices->refresh([$asset]);
            $asset->refresh();
        }

        $price = (float) ($asset->current_price ?? 0);
        if ($price <= 0) {
            return $this->skip($plan, $on, 'No price available for '.$asset->symbol);
        }

        $costCcy = $holding->costCurrency();
        $priceInCost = $this->fx->convert($price, $assetCcy, $costCcy);

        $units = $plan->amount_kind === 'units'
            ? (float) $plan->amount
            : ($priceInCost > 0 ? $this->fx->convert((float) $plan->amount, $plan->currency ?: $costCcy, $costCcy) / $priceInCost : 0.0);

        if ($units <= 0) {
            return $this->skip($plan, $on, 'Movement resolved to zero units');
        }

        $signed = $plan->direction === 'in' ? $units : -$units;
        $newQty = (float) $holding->quantity + $signed;

        if ($newQty < 0) {
            return $this->skip($plan, $on, 'Would take the position below zero');
        }

        if ($plan->direction === 'in') {
            // Weighted average over every share: (old shares × old avg + bought × price) ÷ total.
            // A position with no recorded cost basis contributes zero, matching
            // Holding::costBasis(); the user can set the real buy price on the position.
            $oldAvg = (float) ($holding->average_cost ?? 0);
            $holding->average_cost = $newQty > 0
                ? ((float) $holding->quantity * $oldAvg + $units * $priceInCost) / $newQty
                : $priceInCost;
            $holding->cost_currency = $holding->cost_currency ?: $costCcy;
        }

        $holding->quantity = $newQty;
        $holding->save();

        return $this->record($plan, $on, 'applied', [
            'units_delta' => $signed,
            'cash_amount' => $plan->amount_kind === 'cash' ? (float) $plan->amount : $units * $priceInCost,
            'cash_currency' => $plan->amount_kind === 'cash' ? ($plan->currency ?: $costCcy) : $costCcy,
            'unit_price' => $price,
            'asset_currency' => $assetCcy,
            'resulting_quantity' => $newQty,
            'resulting_avg_cost' => $holding->average_cost,
        ]);
    }

    protected function applyDebt(Plan $plan, Holding $holding, CarbonImmutable $on): PlanRun
    {
        $assetCcy = $holding->asset->currency ?: 'USD';
        $cash = $this->fx->convert((float) $plan->amount, $plan->currency ?: $assetCcy, $assetCcy);

        $newDebt = $plan->direction === 'in'
            ? (float) $holding->debt + $cash
            : (float) $holding->debt - $cash;

        $note = null;
        if ($newDebt < 0) {
            $newDebt = 0.0;
            $note = 'Debt cleared';
        }

        $holding->debt = $newDebt;
        $holding->save();

        return $this->record($plan, $on, 'applied', [
            'cash_amount' => (float) $plan->amount,
            'cash_currency' => $plan->currency ?: $assetCcy,
            'asset_currency' => $assetCcy,
            'resulting_debt' => $newDebt,
            'note' => $note,
        ]);
    }

    protected function applyValue(Plan $plan, Holding $holding, CarbonImmutable $on): PlanRun
    {
        $assetCcy = $holding->asset->currency ?: 'USD';
        $current = (float) ($holding->manual_value ?? 0);

        // A percentage moves the value by a share of what it is worth right now
        // (e.g. +2% appreciation a year); a cash amount moves it by a fixed sum.
        if ($plan->amount_kind === 'percent') {
            $delta = $current * ((float) $plan->amount / 100);
            $note = ($plan->direction === 'in' ? '+' : '−').rtrim(rtrim(number_format($plan->amount, 4, '.', ''), '0'), '.').'%';
        } else {
            $delta = $this->fx->convert((float) $plan->amount, $plan->currency ?: $assetCcy, $assetCcy);
            $note = null;
        }

        $newValue = $plan->direction === 'in' ? $current + $delta : $current - $delta;

        if ($newValue < 0) {
            return $this->skip($plan, $on, 'Would take the value below zero');
        }

        $holding->manual_value = $newValue;
        $holding->save();

        return $this->record($plan, $on, 'applied', [
            'cash_amount' => $plan->amount_kind === 'percent' ? $delta : (float) $plan->amount,
            'cash_currency' => $plan->amount_kind === 'percent' ? $assetCcy : ($plan->currency ?: $assetCcy),
            'asset_currency' => $assetCcy,
            'resulting_value' => $newValue,
            'note' => $note,
        ]);
    }

    protected function skip(Plan $plan, CarbonImmutable $on, string $reason): PlanRun
    {
        Log::channel('single')->info('Plan movement skipped', [
            'plan_id' => $plan->id,
            'reason' => $reason,
        ]);

        return $this->record($plan, $on, 'skipped', ['note' => $reason]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function record(Plan $plan, CarbonImmutable $on, string $status, array $attributes): PlanRun
    {
        return $plan->runs()->create(array_merge([
            'ran_on' => $on->toDateString(),
            'status' => $status,
        ], $attributes));
    }
}
