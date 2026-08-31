<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Holding;
use App\Models\Plan;
use App\Services\PlanRunner;
use App\Services\PortfolioService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    private const CURRENCIES = ['EUR', 'USD', 'GBP', 'CHF', 'JPY', 'CAD', 'AUD', 'SEK', 'NOK', 'DKK', 'PLN'];

    public function index(Request $request)
    {
        $plans = Plan::query()
            ->whereHas('holding.account', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with(['holding.asset', 'holding.account'])
            ->withCount('runs')
            ->orderByDesc('active')
            ->orderBy('next_run_on')
            ->get();

        return view('plans.index', ['plans' => $plans]);
    }

    public function create(Request $request)
    {
        return view('plans.create', $this->formData($request));
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        $holding = $request->user()->holdings()->with('asset')->findOrFail($data['holding_id']);
        $this->assertTargetFitsHolding($holding, $data);

        $plan = Plan::create([
            'holding_id' => $holding->id,
            'target' => $data['target'],
            'direction' => $data['direction'],
            'amount_kind' => $data['amount_kind'],
            'amount' => $data['amount'],
            'currency' => $data['amount_kind'] === 'cash'
                ? strtoupper($data['currency'] ?: $request->user()->currency())
                : null,
            'frequency' => $data['frequency'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'] ?? null,
            'next_run_on' => $data['starts_on'],
            'active' => $request->boolean('active', true),
            'note' => $data['note'] ?? null,
        ]);

        return redirect()->route('plans.show', $plan)->with('status', 'Plan created.');
    }

    public function show(Request $request, Plan $plan)
    {
        $this->authorizePlan($request, $plan);
        $plan->load(['holding.asset', 'holding.account']);

        return view('plans.show', [
            'plan' => $plan,
            'runs' => $plan->runs()->limit(60)->get(),
        ]);
    }

    public function edit(Request $request, Plan $plan)
    {
        $this->authorizePlan($request, $plan);

        return view('plans.edit', array_merge($this->formData($request), ['plan' => $plan]));
    }

    public function update(Request $request, Plan $plan)
    {
        $this->authorizePlan($request, $plan);

        $data = $this->validatePayload($request);

        $holding = $request->user()->holdings()->with('asset')->findOrFail($data['holding_id']);
        $this->assertTargetFitsHolding($holding, $data);

        $plan->fill([
            'holding_id' => $holding->id,
            'target' => $data['target'],
            'direction' => $data['direction'],
            'amount_kind' => $data['amount_kind'],
            'amount' => $data['amount'],
            'currency' => $data['amount_kind'] === 'cash'
                ? strtoupper($data['currency'] ?: $request->user()->currency())
                : null,
            'frequency' => $data['frequency'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'] ?? null,
            'active' => $request->boolean('active'),
            'note' => $data['note'] ?? null,
        ]);

        // Re-anchor the schedule while nothing has executed yet.
        if ($plan->runs()->count() === 0) {
            $plan->next_run_on = $data['starts_on'];
        }

        $plan->save();

        return redirect()->route('plans.show', $plan)->with('status', 'Plan updated.');
    }

    public function run(Request $request, Plan $plan, PlanRunner $runner, PortfolioService $portfolio)
    {
        $this->authorizePlan($request, $plan);

        $plan->load(['holding.asset', 'holding.account']);
        $run = $runner->execute($plan, CarbonImmutable::today());
        $portfolio->snapshot($request->user());

        $message = $run->wasApplied()
            ? 'Movement applied.'
            : 'Movement skipped: '.$run->note;

        return redirect()->route('plans.show', $plan)->with('status', $message);
    }

    public function runDue(Request $request, PlanRunner $runner)
    {
        $result = $runner->runDueForUser($request->user(), CarbonImmutable::today());

        return redirect()->route('plans.index')
            ->with('status', "Plans: {$result['applied']} applied, {$result['skipped']} skipped.");
    }

    public function destroy(Request $request, Plan $plan)
    {
        $this->authorizePlan($request, $plan);
        $plan->delete();

        return redirect()->route('plans.index')->with('status', 'Plan deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request): array
    {
        $holdings = $request->user()->holdings()->with(['asset', 'account'])->get()
            ->sortBy(fn ($h) => $h->account->name.$h->asset->symbol)
            ->values();

        $meta = $holdings->map(fn ($h) => [
            'id' => $h->id,
            'symbol' => $h->asset->symbol,
            'account' => $h->account->name,
            'type' => $h->typeLabel(),
            'priced' => in_array($h->asset->type, Asset::PRICED_TYPES, true),
            'realestate' => $h->asset->type === 'realestate',
            'cost_currency' => strtoupper($h->costCurrency()),
            'quantity' => (float) $h->quantity,
            'debt' => (float) $h->debt,
        ])->values();

        return [
            'holdings' => $holdings,
            'holdingsMeta' => $meta,
            'frequencies' => Plan::FREQUENCIES,
            'currencyOptions' => self::CURRENCIES,
            'today' => CarbonImmutable::today()->toDateString(),
            'baseCurrency' => $request->user()->currency(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'holding_id' => ['required', 'integer'],
            'target' => ['required', Rule::in(Plan::TARGETS)],
            'direction' => ['required', Rule::in(Plan::DIRECTIONS)],
            'amount_kind' => ['required', Rule::in(Plan::KINDS)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3', Rule::requiredIf(fn () => $request->input('amount_kind') === 'cash')],
            'frequency' => ['required', Rule::in(Plan::FREQUENCIES)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['amount_kind'] === 'units' && $data['target'] !== 'quantity') {
            throw ValidationException::withMessages([
                'amount_kind' => 'A number of units only applies to a buy or sell.',
            ]);
        }

        if ($data['amount_kind'] === 'percent' && $data['target'] !== 'value') {
            throw ValidationException::withMessages([
                'amount_kind' => 'A percentage only applies to a value adjustment.',
            ]);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertTargetFitsHolding(Holding $holding, array $data): void
    {
        $type = $holding->asset->type;
        $allowed = match (true) {
            in_array($type, Asset::PRICED_TYPES, true) => ['quantity'],
            $type === 'realestate' => ['debt', 'value'],
            default => ['value'], // cash, other
        };

        if (! in_array($data['target'], $allowed, true)) {
            throw ValidationException::withMessages([
                'target' => 'That movement type is not available for this position.',
            ]);
        }
    }

    private function authorizePlan(Request $request, Plan $plan): void
    {
        abort_unless($plan->holding->account->user_id === $request->user()->id, 403);
    }
}
