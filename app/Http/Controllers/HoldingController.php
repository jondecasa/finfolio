<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Holding;
use App\Services\PortfolioService;
use App\Services\PriceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HoldingController extends Controller
{
    public function __construct(
        protected PriceService $prices,
        protected PortfolioService $portfolio,
    ) {}

    public function create(Request $request)
    {
        $accounts = $request->user()->accounts()->get();

        return view('holdings.create', [
            'accounts' => $accounts,
            'selectedAccount' => $request->query('account'),
            'categories' => config('finfolio.categories'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        $account = $request->user()->accounts()->findOrFail($data['account_id']);

        $asset = $this->prices->resolveAsset([
            'type' => $data['type'],
            'symbol' => $data['symbol'],
            'name' => $data['name'] ?? $data['symbol'],
            'exchange' => $data['exchange'] ?? null,
            'currency' => $data['currency'] ?? null,
            'provider_id' => $data['provider_id'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
        ]);

        $isManual = in_array($data['type'], Asset::MANUAL_TYPES, true);

        $holding = Holding::updateOrCreate(
            ['account_id' => $account->id, 'asset_id' => $asset->id],
            [
                'quantity' => $data['quantity'],
                'average_cost' => $data['average_cost'] ?? null,
                'cost_currency' => $data['cost_currency'] ?: $request->user()->currency(),
                'manual_value' => $isManual ? $data['manual_price'] : null,
                'debt' => $data['type'] === 'realestate' ? ($data['debt'] ?? 0) : 0,
                'notes' => $data['notes'] ?? null,
            ],
        );

        $this->portfolio->snapshot($request->user());

        return redirect()->route('analytics')->with('status', "{$asset->symbol} added to {$account->name}.");
    }

    public function show(Holding $holding)
    {
        $this->authorizeHolding($holding);

        return redirect()->route('holdings.edit', $holding);
    }

    public function edit(Request $request, Holding $holding)
    {
        $this->authorizeHolding($holding);
        $holding->load(['asset', 'account']);

        return view('holdings.edit', [
            'holding' => $holding,
            'accounts' => $request->user()->accounts()->get(),
        ]);
    }

    public function update(Request $request, Holding $holding)
    {
        $this->authorizeHolding($holding);

        $isManual = in_array($holding->asset->type, Asset::MANUAL_TYPES, true);

        $data = $request->validate([
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $request->user()->id)],
            'category' => ['nullable', Rule::in(Holding::RECATEGORISABLE)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'average_cost' => ['nullable', 'numeric', 'gte:0'],
            'cost_currency' => ['nullable', 'string', 'size:3'],
            'manual_value' => [$isManual ? 'required' : 'nullable', 'numeric', 'gte:0'],
            'debt' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $holding->update([
            'account_id' => $data['account_id'],
            'category' => $holding->canRecategorise()
                ? (($data['category'] ?? null) === $holding->asset->type ? null : ($data['category'] ?? null))
                : $holding->category,
            'quantity' => $data['quantity'],
            'average_cost' => $data['average_cost'] ?? null,
            'cost_currency' => strtoupper($data['cost_currency'] ?? '') ?: $holding->costCurrency(),
            'manual_value' => $isManual ? $data['manual_value'] : null,
            'debt' => $holding->asset->type === 'realestate' ? ($data['debt'] ?? 0) : 0,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->portfolio->snapshot($request->user());

        return redirect()->route('analytics')->with('status', "{$holding->asset->symbol} updated.");
    }

    public function destroy(Request $request, Holding $holding)
    {
        $this->authorizeHolding($holding);
        $symbol = $holding->asset->symbol;
        $holding->delete();

        $this->portfolio->snapshot($request->user());

        return redirect()->route('analytics')->with('status', "{$symbol} removed.");
    }

    protected function validatePayload(Request $request): array
    {
        $manualTypes = Asset::MANUAL_TYPES;

        $data = $request->validate([
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $request->user()->id)],
            'type' => ['required', Rule::in(['crypto', 'stock', 'etf', 'index', 'fund', 'commodity', 'realestate', 'cash', 'other'])],
            'symbol' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:120'],
            'exchange' => ['nullable', 'string', 'max:60'],
            'currency' => ['nullable', 'string', 'size:3'],
            'provider_id' => ['nullable', 'string', 'max:120'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'average_cost' => ['nullable', 'numeric', 'gte:0'],
            'cost_currency' => ['nullable', 'string', 'size:3'],
            'manual_price' => ['nullable', 'numeric', 'gte:0', Rule::requiredIf(fn () => in_array($request->input('type'), $manualTypes, true))],
            'debt' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $data['name'] = $data['name'] ?: strtoupper($data['symbol']);
        $data['cost_currency'] = strtoupper($data['cost_currency'] ?? '') ?: null;

        // Searchable assets get their currency from the price provider. A
        // manually-valued asset falls back to the user's base currency.
        if (in_array($data['type'], $manualTypes, true)) {
            $data['currency'] = strtoupper($data['currency'] ?: $request->user()->currency());
        } else {
            $data['currency'] = $data['currency'] ? strtoupper($data['currency']) : null;
        }

        return $data;
    }

    protected function authorizeHolding(Holding $holding): void
    {
        abort_unless($holding->account->user_id === request()->user()->id, 403);
    }
}
