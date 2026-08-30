@php
    $prefill = [
        'type' => request('type'),
        'symbol' => request('symbol'),
        'name' => request('name'),
        'provider_id' => request('provider_id'),
        'exchange' => request('exchange'),
        'currency' => request('currency'),
        'logo_url' => request('logo_url'),
    ];
    $hasPrefill = filled($prefill['symbol']);
    $prefillType = in_array(request('type'), array_keys($categories)) ? request('type') : 'stock';
    $baseCurrency = auth()->user()->currency();
    $currencyOptions = ['EUR', 'USD', 'GBP', 'CHF', 'JPY', 'CAD', 'AUD', 'SEK', 'NOK', 'DKK', 'PLN'];
    $nonSearchable = collect($categories)->reject(fn ($c) => $c['searchable'] ?? true)->keys()->values();
@endphp

<x-layouts.mobile heading="Add position" title="Finfolio · Add position" :back="url()->previous()">
  <div class="app-pad lg:mx-auto lg:max-w-xl">
    <form method="POST" action="{{ route('holdings.store') }}" class="space-y-5"
          x-data="{
              category: @js($prefillType),
              nonSearchable: @js($nonSearchable),
              asset: @js($hasPrefill ? $prefill : null),
              manual: { name: '', symbol: '', currency: @js($baseCurrency), price: '', purchase: '', debt: '' },
              avgCost: '{{ old('average_cost') }}',
              qty: {{ old('quantity', 1) }},
              query: '',
              results: [],
              loading: false,
              open: false,
              timer: null,
              get searchable() { return !this.nonSearchable.includes(this.category); },
              get isCash() { return this.category === 'cash'; },
              get isRealEstate() { return this.category === 'realestate'; },
              get unitless() { return this.isCash || this.isRealEstate; },
              get ready() {
                  if (this.searchable) return !!this.asset;
                  if (this.isCash) return this.manual.price !== '' && Number(this.manual.price) > 0;
                  return this.manual.name.trim() && this.manual.price !== '';
              },
              get outSymbol() {
                  if (this.searchable) return this.asset ? this.asset.symbol : '';
                  if (this.isCash) return 'CASH-' + this.manual.currency;
                  return (this.manual.symbol.trim() || this.manual.name.trim().toUpperCase().replace(/[^A-Z0-9]+/g, '-')).slice(0, 24);
              },
              get outName() {
                  if (this.searchable) return this.asset ? this.asset.name : '';
                  if (this.isCash) return this.manual.currency + ' cash';
                  return this.manual.name.trim();
              },
              get outCurrency() { return this.searchable ? (this.asset && this.asset.currency || '') : this.manual.currency; },
              get outAvgCost() { return this.isRealEstate ? this.manual.purchase : (this.isCash ? '' : this.avgCost); },
              get outDebt() { return this.isRealEstate ? (this.manual.debt || '') : ''; },
              setCategory(c) {
                  this.category = c;
                  this.asset = null; this.query = ''; this.results = []; this.open = false;
                  if (this.unitless) this.qty = 1;
              },
              onInput() {
                  clearTimeout(this.timer);
                  if (this.query.trim().length < 1) { this.results = []; this.open = false; return; }
                  this.timer = setTimeout(() => this.run(), 280);
              },
              async run() {
                  this.loading = true; this.open = true;
                  try {
                      const url = new URL('{{ route('api.search') }}', window.location.origin);
                      url.searchParams.set('q', this.query.trim());
                      url.searchParams.set('type', this.category);
                      const res = await fetch(url, { headers: { Accept: 'application/json' } });
                      this.results = (await res.json()).results || [];
                  } finally { this.loading = false; }
              },
              pick(row) { this.asset = row; this.query = ''; this.results = []; this.open = false; },
          }">
        @csrf

        {{-- Account --}}
        <div>
            <label class="mb-1.5 block text-sm font-semibold text-muted">Account</label>
            <select name="account_id" class="field" required>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected($selectedAccount == $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Category --}}
        <div>
            <label class="mb-1.5 block text-sm font-semibold text-muted">What are you adding?</label>
            <div class="flex flex-wrap gap-2">
                @foreach ($categories as $key => $cat)
                    <button type="button" @click="setCategory('{{ $key }}')" class="tab"
                            :class="category === '{{ $key }}' ? 'tab-active' : ''">{{ $cat['label'] }}</button>
                @endforeach
            </div>
        </div>

        {{-- Searchable asset picker --}}
        <div x-show="searchable">
            <label class="mb-1.5 block text-sm font-semibold text-muted">Asset</label>

            <div x-show="!asset">
                <input type="search" class="field" placeholder="Search ticker or name…" x-model="query" @input="onInput()">
                <div class="mt-2 space-y-1.5" x-show="open" x-cloak>
                    <div x-show="loading" class="px-1 py-2 text-xs text-muted">Searching…</div>
                    <template x-for="row in results" :key="row.type + row.symbol">
                        <button type="button" @click="pick(row)"
                                class="flex w-full items-center gap-3 rounded-xl bg-ink-700 p-2.5 text-left hover:bg-ink-600">
                            <span class="logo-bubble">
                                <template x-if="row.logo_url"><img :src="row.logo_url" class="h-full w-full object-cover" alt=""></template>
                                <template x-if="!row.logo_url"><span x-text="row.symbol.slice(0,3)"></span></template>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold" x-text="row.name"></span>
                                <span class="block text-xs text-muted"><span x-text="row.symbol"></span> · <span x-text="row.type"></span></span>
                            </span>
                        </button>
                    </template>
                    <p x-show="!loading && results.length === 0" class="px-1 py-2 text-xs text-muted">No matches.</p>
                </div>
            </div>

            <div x-show="asset" x-cloak class="flex items-center gap-3 rounded-2xl bg-ink-700 p-3">
                <span class="logo-bubble">
                    <template x-if="asset && asset.logo_url"><img :src="asset.logo_url" class="h-full w-full object-cover" alt=""></template>
                    <template x-if="asset && !asset.logo_url"><span x-text="asset.symbol.slice(0,3)"></span></template>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="truncate font-semibold" x-text="asset && asset.name"></div>
                    <div class="text-xs text-muted"><span x-text="asset && asset.symbol"></span> · <span x-text="asset && asset.type"></span></div>
                </div>
                <button type="button" class="text-sm text-muted hover:text-white" @click="asset = null">Change</button>
            </div>
        </div>

        {{-- Manual entry (Real estate, Cash, Other) --}}
        <div x-show="!searchable" x-cloak class="space-y-4">
            <div x-show="!isCash">
                <label class="mb-1.5 block text-sm font-semibold text-muted">Name</label>
                <input type="text" class="field" x-model="manual.name"
                       :placeholder="isRealEstate ? 'e.g. Flat in Madrid' : 'e.g. Grandma\'s gold coins'" maxlength="120">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div x-show="!unitless">
                    <label class="mb-1.5 block text-sm font-semibold text-muted">Ticker <span class="text-muted/60">(optional)</span></label>
                    <input type="text" class="field" x-model="manual.symbol" placeholder="auto" maxlength="24">
                </div>
                <div :class="unitless ? 'col-span-2' : ''">
                    <label class="mb-1.5 block text-sm font-semibold text-muted">Currency</label>
                    <select class="field" x-model="manual.currency">
                        @foreach ($currencyOptions as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Real estate: purchase / current / mortgage --}}
            <template x-if="isRealEstate">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-muted">Purchase price</label>
                            <input type="number" step="any" min="0" class="field" x-model="manual.purchase" inputmode="decimal" placeholder="0.00">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-muted">Current value</label>
                            <input type="number" step="any" min="0" class="field" x-model="manual.price" inputmode="decimal" placeholder="0.00">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-muted">Mortgage / debt <span class="text-muted/60">(optional)</span></label>
                        <input type="number" step="any" min="0" class="field" x-model="manual.debt" inputmode="decimal" placeholder="0.00">
                        <p class="mt-1 text-xs text-muted">
                            Net worth counts <span class="font-semibold text-white" x-text="window.Finfolio.formatCurrency((Number(manual.price)||0) - (Number(manual.debt)||0), manual.currency)"></span>;
                            the debt shows under Liabilities. Return is measured purchase → current value.
                        </p>
                    </div>
                </div>
            </template>

            {{-- Cash / Other: single value field --}}
            <div x-show="!isRealEstate">
                <label class="mb-1.5 block text-sm font-semibold text-muted"
                       x-text="isCash ? 'Amount' : 'Current value per unit'"></label>
                <input type="number" step="any" min="0" class="field" x-model="manual.price" inputmode="decimal" placeholder="0.00">
                <p class="mt-1 text-xs text-muted"
                   x-text="isCash ? 'Held as cash — converted to your base currency.' : 'This value does not update automatically.'"></p>
            </div>
        </div>

        {{-- Quantity + cost (only for searchable assets and 'other') --}}
        <div class="grid grid-cols-2 gap-3" x-show="!unitless">
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-muted">Quantity</label>
                <input type="number" step="any" min="0" class="field" x-model="qty" inputmode="decimal">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-muted">Avg. buy price</label>
                <input type="number" step="any" min="0" class="field" x-model="avgCost" inputmode="decimal">
            </div>
        </div>
        <input type="hidden" name="quantity" :value="qty">
        <input type="hidden" name="average_cost" :value="outAvgCost">
        <input type="hidden" name="debt" :value="outDebt">

        <div>
            <label class="mb-1.5 block text-sm font-semibold text-muted">Note (optional)</label>
            <input type="text" name="notes" class="field" value="{{ old('notes') }}" maxlength="255">
        </div>

        {{-- Hidden payload --}}
        <input type="hidden" name="type" :value="category">
        <input type="hidden" name="symbol" :value="outSymbol">
        <input type="hidden" name="name" :value="outName">
        <input type="hidden" name="currency" :value="outCurrency">
        <input type="hidden" name="provider_id" :value="(searchable && asset && asset.provider_id) || ''">
        <input type="hidden" name="exchange" :value="(searchable && asset && asset.exchange) || ''">
        <input type="hidden" name="logo_url" :value="(searchable && asset && asset.logo_url) || ''">
        <input type="hidden" name="manual_price" :value="searchable ? '' : manual.price">

        <button class="btn-primary w-full" :disabled="!ready" :class="!ready ? 'opacity-40 pointer-events-none' : ''">Add position</button>
    </form>
  </div>
</x-layouts.mobile>
