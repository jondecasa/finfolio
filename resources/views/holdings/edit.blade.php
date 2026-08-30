@php
    $asset = $holding->asset;
    $currency = $asset->currency ?? 'USD';
    $source = $asset->priceSource();
    $num = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 8, '.', ''), '0'), '.');
    $categories = config('finfolio.categories', []);
    $costCcy = strtoupper($holding->costCurrency());
    $costCcys = collect([auth()->user()->currency(), $currency, 'EUR', 'USD', 'GBP', 'CHF', 'JPY', 'CAD', 'AUD', 'SEK', 'NOK', 'DKK', 'PLN'])
        ->map(fn ($c) => strtoupper($c))->filter()->unique()->values();
@endphp

<x-layouts.mobile heading="Edit position" title="Finfolio · Edit position" :back="route('analytics')">
  <div class="lg:mx-auto lg:max-w-xl">
    <div class="app-pad">
        <{{ $source ? 'a' : 'div' }}
            @if ($source) href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" @endif
            class="card flex items-center gap-3 {{ $source ? 'transition hover:bg-ink-700' : '' }}">
            <span class="logo-bubble">
                @if ($asset->logo_url)
                    <img src="{{ $asset->logo_url }}" alt="" class="h-full w-full object-cover">
                @else
                    {{ \Illuminate\Support\Str::substr($asset->symbol, 0, 3) }}
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <div class="truncate font-semibold">{{ $asset->name }}</div>
                <div class="text-xs text-muted">
                    {{ $asset->symbol }} · {{ $holding->typeLabel() }}
                    @if ($holding->grossValue() > 0)
                        · {{ \App\Support\Money::format($holding->grossValue(), $currency) }}
                        @if ($holding->debtAmount() > 0)
                            <span class="value-down">− {{ \App\Support\Money::format($holding->debtAmount(), $currency) }} debt</span>
                        @endif
                    @endif
                </div>
                <div class="mt-1 text-xs">
                    @if ($source)
                        <span class="inline-flex items-center gap-1 text-accent">
                            Quote from {{ $source['name'] }}
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
                        </span>
                        @if ($asset->price_updated_at)
                            <span class="text-muted"> · updated {{ $asset->price_updated_at->diffForHumans() }}</span>
                        @endif
                    @else
                        <span class="text-muted">Manual value — no live source</span>
                    @endif
                </div>
            </div>
            @if ($source)
                <span class="shrink-0 text-muted">&rsaquo;</span>
            @endif
        </{{ $source ? 'a' : 'div' }}>
    </div>

    <form method="POST" action="{{ route('holdings.update', $holding) }}" class="app-pad mt-5 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label class="mb-1.5 block text-sm font-semibold text-muted">Account</label>
            <select name="account_id" class="field" required>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected($holding->account_id == $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
        </div>

        @if ($holding->canRecategorise())
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-muted">Category</label>
                <select name="category" class="field">
                    @foreach (\App\Models\Holding::RECATEGORISABLE as $t)
                        <option value="{{ $t }}" @selected($holding->displayType() === $t)>{{ $categories[$t]['label'] ?? \Illuminate\Support\Str::headline($t) }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-muted">Only changes how it's grouped in Analytics. Pricing stays the same.</p>
            </div>
        @endif

        @if ($asset->type === 'realestate')
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-muted">Purchase price</label>
                    <input type="number" step="any" min="0" name="average_cost" class="field"
                           value="{{ old('average_cost', $num($holding->average_cost)) }}" inputmode="decimal">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-muted">Current value</label>
                    <input type="number" step="any" min="0" name="manual_value" class="field"
                           value="{{ old('manual_value', $num($holding->manual_value)) }}" required inputmode="decimal">
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-muted">Mortgage / debt</label>
                <input type="number" step="any" min="0" name="debt" class="field"
                       value="{{ old('debt', $num($holding->debt)) }}" inputmode="decimal">
                <p class="mt-1 text-xs text-muted">Shown under Liabilities and subtracted from net worth. Return is measured purchase → current value.</p>
            </div>
            <input type="hidden" name="quantity" value="{{ $num($holding->quantity) ?: 1 }}">

        @elseif ($asset->type === 'cash')
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-muted">Amount ({{ $currency }})</label>
                <input type="number" step="any" min="0" name="manual_value" class="field"
                       value="{{ old('manual_value', $num($holding->manual_value)) }}" required inputmode="decimal">
            </div>
            <input type="hidden" name="quantity" value="1">

        @elseif ($asset->type === 'other')
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-muted">Quantity</label>
                    <input type="number" step="any" min="0" name="quantity" class="field"
                           value="{{ old('quantity', $num($holding->quantity)) }}" required inputmode="decimal">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-muted">Current value / unit</label>
                    <input type="number" step="any" min="0" name="manual_value" class="field"
                           value="{{ old('manual_value', $num($holding->manual_value)) }}" required inputmode="decimal">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-muted">Avg. buy price</label>
                    <input type="number" step="any" min="0" name="average_cost" class="field"
                           value="{{ old('average_cost', $num($holding->average_cost)) }}" inputmode="decimal">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-muted">Cost currency</label>
                    <select name="cost_currency" class="field">
                        @foreach ($costCcys as $c)
                            <option value="{{ $c }}" @selected($costCcy === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="text-xs text-muted">What <em>you</em> paid per unit, in the currency you paid in.</p>

        @else
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-muted">Quantity</label>
                    <input type="number" step="any" min="0" name="quantity" class="field"
                           value="{{ old('quantity', $num($holding->quantity)) }}" required inputmode="decimal">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-muted">Avg. buy price</label>
                    <input type="number" step="any" min="0" name="average_cost" class="field"
                           value="{{ old('average_cost', $num($holding->average_cost)) }}" inputmode="decimal">
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-muted">Cost currency</label>
                <select name="cost_currency" class="field">
                    @foreach ($costCcys as $c)
                        <option value="{{ $c }}" @selected($costCcy === $c)>{{ $c }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-muted">
                    The currency <em>you</em> paid in. This asset trades in <span class="font-semibold text-white">{{ strtoupper($asset->currency) }}</span>.
                </p>
            </div>
        @endif

        <div>
            <label class="mb-1.5 block text-sm font-semibold text-muted">Note</label>
            <input type="text" name="notes" class="field" value="{{ old('notes', $holding->notes) }}" maxlength="255">
        </div>

        <button class="btn-primary w-full">Save changes</button>
    </form>

    <div class="app-pad mt-3">
        <x-confirm-form
            :action="route('holdings.destroy', $holding)"
            title="Remove this position?"
            :message="'“'.$asset->name.'” will be removed from '.$holding->account->name.'.'"
            confirm="Remove position"
            trigger="Remove position"
            trigger-class="btn-ghost w-full text-loss" />
    </div>
  </div>
</x-layouts.mobile>
