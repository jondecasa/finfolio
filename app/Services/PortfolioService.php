<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Holding;
use App\Models\PortfolioSnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PortfolioService
{
    public function __construct(protected FxService $fx) {}

    public function baseCurrency(User $user): string
    {
        return $user->currency();
    }

    /**
     * All holdings for a user with asset + account eager-loaded.
     *
     * @return Collection<int, Holding>
     */
    public function holdings(User $user): Collection
    {
        return Holding::query()
            ->with(['asset', 'account'])
            ->whereHas('account', fn ($q) => $q->where('user_id', $user->id))
            ->get();
    }

    /** Net value (gross minus debt) converted to the user's base currency — this is what counts towards net worth. */
    public function holdingValue(Holding $holding, string $base): float
    {
        return $this->fx->convert($holding->netValue(), $holding->asset->currency ?? 'USD', $base);
    }

    /** Gross value (before debt) — used to measure appreciation against cost. */
    public function holdingGross(Holding $holding, string $base): float
    {
        return $this->fx->convert($holding->grossValue(), $holding->asset->currency ?? 'USD', $base);
    }

    /** Debt attached to the holding (e.g. a mortgage), converted to base currency. */
    public function holdingDebt(Holding $holding, string $base): float
    {
        return $this->fx->convert($holding->debtAmount(), $holding->asset->currency ?? 'USD', $base);
    }

    public function holdingInvested(Holding $holding, string $base): float
    {
        return $this->fx->convert($holding->costBasis(), $holding->costCurrency(), $base);
    }

    /** Position return %, measured consistently in the user's base currency. */
    public function holdingGainPct(Holding $holding, string $base): ?float
    {
        $invested = $this->holdingInvested($holding, $base);

        return $invested > 0
            ? ($this->holdingGross($holding, $base) - $invested) / $invested * 100
            : null;
    }

    /**
     * Cash equity actually put into the position, converted to base currency:
     * just the mortgage down payment for real estate (or the full purchase
     * price if bought outright), the full cost basis for everything else.
     */
    public function holdingEquityInvested(Holding $holding, string $base): float
    {
        return $this->fx->convert($holding->investedEquity(), $holding->costCurrency(), $base);
    }

    /** Profit % against the cash equity invested (net of debt), not the full cost basis. */
    public function holdingEquityGainPct(Holding $holding, string $base): ?float
    {
        $equity = $this->holdingEquityInvested($holding, $base);

        return $equity > 0
            ? ($this->holdingValue($holding, $base) - $equity) / $equity * 100
            : null;
    }

    /** Net value of the holding at the previous market close, in base currency. */
    public function holdingPreviousValue(Holding $holding, string $base): float
    {
        $prevGross = $holding->manual_value !== null
            ? (float) $holding->manual_value
            : (float) $holding->quantity * (float) ($holding->asset->previous_close ?? $holding->asset->current_price ?? 0);
        $prevGross *= $holding->ownershipFraction();

        return $this->fx->convert($prevGross - $holding->debtAmount(), $holding->asset->currency ?? 'USD', $base);
    }

    /**
     * Headline numbers for the Home / Net Worth screens.
     *
     * @return array<string, mixed>
     */
    public function overview(User $user): array
    {
        $base = $this->baseCurrency($user);
        $holdings = $this->holdings($user);

        $accounts = $user->accounts()->get()->keyBy('id');
        $perAccount = [];

        foreach ($accounts as $account) {
            $perAccount[$account->id] = [
                'account' => $account,
                'value' => 0.0,
                'gross' => 0.0,
                'invested' => 0.0,
                'debt' => 0.0,
                'previous' => 0.0,
                'equity_invested' => 0.0,
                'equity_value' => 0.0,
                'positions' => 0,
            ];
        }

        $totalValue = $totalGross = $totalInvested = $totalDebt = $totalPrevious = $cashTotal = 0.0;
        $totalEquityInvested = $totalEquityValue = 0.0;
        $debtHoldings = [];

        foreach ($holdings as $holding) {
            $value = $this->holdingValue($holding, $base);
            $gross = $this->holdingGross($holding, $base);
            $invested = $this->holdingInvested($holding, $base);
            $debt = $this->holdingDebt($holding, $base);
            $previous = $this->holdingPreviousValue($holding, $base);
            $isCash = $holding->asset->type === 'cash';
            $equityInvested = $isCash ? 0.0 : $this->holdingEquityInvested($holding, $base);

            $totalValue += $value;
            $totalGross += $gross;
            $totalInvested += $invested;
            $totalDebt += $debt;
            $totalPrevious += $previous;
            $totalEquityInvested += $equityInvested;

            if ($isCash) {
                $cashTotal += $value;
            } else {
                $totalEquityValue += $value;
            }

            if ($debt > 0) {
                $debtHoldings[] = ['holding' => $holding, 'debt' => $debt];
            }

            $bucket = &$perAccount[$holding->account_id];
            $bucket['value'] += $value;
            $bucket['gross'] += $gross;
            $bucket['invested'] += $invested;
            $bucket['debt'] += $debt;
            $bucket['previous'] += $previous;
            $bucket['equity_invested'] += $equityInvested;
            $bucket['equity_value'] += $isCash ? 0.0 : $value;
            $bucket['positions']++;
            unset($bucket);
        }

        $dayChange = $totalValue - $totalPrevious;

        return [
            'currency' => $base,
            'total_value' => $totalValue,
            'total_gross' => $totalGross,
            'total_invested' => $totalInvested,
            'total_debt' => $totalDebt,
            'total_gain' => $totalGross - $totalInvested,
            'total_gain_pct' => $totalInvested > 0 ? ($totalGross - $totalInvested) / $totalInvested * 100 : null,
            'cash_total' => $cashTotal,
            'total_equity_invested' => $totalEquityInvested,
            'total_equity_gain' => $totalEquityValue - $totalEquityInvested,
            'total_equity_gain_pct' => $totalEquityInvested > 0 ? ($totalEquityValue - $totalEquityInvested) / $totalEquityInvested * 100 : null,
            'day_change' => $dayChange,
            'day_change_pct' => $totalPrevious > 0 ? $dayChange / $totalPrevious * 100 : null,
            'positions_count' => $holdings->count(),
            'debt_holdings' => collect($debtHoldings)->sortByDesc('debt')->values(),
            'accounts' => collect($perAccount)->map(function ($b) {
                $dc = $b['value'] - $b['previous'];
                $equityGain = $b['equity_value'] - $b['equity_invested'];

                return [
                    'account' => $b['account'],
                    'value' => $b['value'],
                    'gross' => $b['gross'],
                    'invested' => $b['invested'],
                    'debt' => $b['debt'],
                    'gain' => $b['gross'] - $b['invested'],
                    'gain_pct' => $b['invested'] > 0 ? ($b['gross'] - $b['invested']) / $b['invested'] * 100 : null,
                    'equity_invested' => $b['equity_invested'],
                    'equity_gain' => $equityGain,
                    'equity_gain_pct' => $b['equity_invested'] > 0 ? $equityGain / $b['equity_invested'] * 100 : null,
                    'day_change' => $dc,
                    'day_change_pct' => $b['previous'] > 0 ? $dc / $b['previous'] * 100 : null,
                    'positions' => $b['positions'],
                ];
            })->sortByDesc('value')->values(),
            'updated_at' => $holdings->max(fn ($h) => $h->asset->price_updated_at),
        ];
    }

    /**
     * Allocation breakdown for the Analytics screen.
     *
     * @return array<string, mixed>
     */
    public function allocation(User $user, ?int $accountId = null): array
    {
        $base = $this->baseCurrency($user);
        $holdings = $this->holdings($user)
            ->when($accountId, fn ($c) => $c->where('account_id', $accountId));
        $total = 0.0;

        $positions = $holdings->map(function (Holding $holding) use ($base, &$total) {
            $value = $this->holdingValue($holding, $base);
            $total += $value;

            return [
                'holding' => $holding,
                'asset' => $holding->asset,
                'account' => $holding->account,
                'symbol' => $holding->asset->symbol,
                'name' => $holding->asset->name,
                'type' => $holding->displayType(),
                'logo_url' => $holding->asset->logo_url,
                'value' => $value,
                'quantity' => (float) $holding->quantity,
                'day_change_pct' => $holding->asset->dayChangePct(),
                'gain_pct' => $this->holdingGainPct($holding, $base),
            ];
        })->values();

        $positions = $positions->map(function ($p) use ($total) {
            $p['weight'] = $total > 0 ? $p['value'] / $total * 100 : 0;

            return $p;
        })->sortByDesc('value')->values();

        $byType = $positions->groupBy('type')->map(function ($group, $type) use ($total) {
            $sum = $group->sum('value');

            return [
                'key' => $type,
                'label' => config("finfolio.categories.$type.label", ucfirst($type)),
                'value' => $sum,
                'weight' => $total > 0 ? $sum / $total * 100 : 0,
                'count' => $group->count(),
            ];
        })->sortByDesc('value')->values();

        return [
            'currency' => $base,
            'total' => $total,
            'positions' => $positions,
            'by_type' => $byType,
        ];
    }

    /**
     * Net-worth time series for the chart.
     *
     * @return array<string, mixed>
     */
    public function series(User $user, string $range = '1W', ?int $accountId = null): array
    {
        $base = $this->baseCurrency($user);
        $ranges = config('finfolio.ranges');
        $spec = $ranges[$range] ?? $ranges['1W'];

        $now = CarbonImmutable::now();
        $from = match (true) {
            $spec['days'] === null => null,
            $spec['days'] === 'ytd' => $now->startOfYear(),
            default => $now->subDays((int) $spec['days']),
        };

        $query = PortfolioSnapshot::query()
            ->where('user_id', $user->id)
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId), fn ($q) => $q->whereNull('account_id'))
            ->when($from, fn ($q) => $q->where('captured_at', '>=', $from))
            ->orderBy('captured_at');

        $points = $query->get(['captured_at', 'value'])
            ->map(fn ($s) => [
                't' => $s->captured_at->toIso8601String(),
                'v' => round((float) $s->value, 2),
            ])
            ->values();

        // Always finish on the current live value so the chart matches the header.
        $liveValue = $accountId
            ? collect($this->overview($user)['accounts'])->firstWhere('account.id', $accountId)['value'] ?? null
            : $this->overview($user)['total_value'];

        if ($liveValue !== null) {
            $points->push(['t' => $now->toIso8601String(), 'v' => round($liveValue, 2)]);
        }

        if ($points->count() === 1) {
            $only = $points->first();
            $points->prepend(['t' => $now->subDay()->toIso8601String(), 'v' => $only['v']]);
        }

        $first = $points->first();
        $last = $points->last();
        $change = $last && $first ? $last['v'] - $first['v'] : 0;

        return [
            'currency' => $base,
            'range' => $range,
            'points' => $points->values(),
            'start_value' => $first['v'] ?? 0,
            'end_value' => $last['v'] ?? 0,
            'change' => $change,
            'change_pct' => ($first && $first['v'] != 0) ? $change / $first['v'] * 100 : 0,
        ];
    }

    /**
     * Persist a snapshot row for "now" (aggregate + one per account).
     */
    public function snapshot(User $user, ?CarbonImmutable $at = null): void
    {
        $at = $at ?: CarbonImmutable::now();
        $overview = $this->overview($user);
        $base = $overview['currency'];

        PortfolioSnapshot::updateOrCreate(
            ['user_id' => $user->id, 'account_id' => null, 'captured_at' => $at],
            ['value' => $overview['total_value'], 'invested' => $overview['total_invested'], 'currency' => $base],
        );

        foreach ($overview['accounts'] as $row) {
            PortfolioSnapshot::updateOrCreate(
                ['user_id' => $user->id, 'account_id' => $row['account']->id, 'captured_at' => $at],
                ['value' => $row['value'], 'invested' => $row['invested'], 'currency' => $base],
            );
        }
    }
}
