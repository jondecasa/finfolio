<x-layouts.mobile heading="New alert" title="Finfolio · New alert" :back="route('alerts.index')">
  <div class="app-pad lg:mx-auto lg:max-w-xl">
    <form method="POST" action="{{ route('alerts.store') }}" class="space-y-5"
          x-data="{ asset: null, condition: @js(old('condition', 'above')), targetPrice: @js(old('target_price', '')) }"
          @asset-selected="asset = $event.detail">
        @csrf

        {{-- Asset picker --}}
        <div x-show="!asset">
            <label class="mb-1.5 block text-sm font-semibold text-muted">Asset</label>
            <div x-data="assetSearch()">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m21 21-4-4"/></svg>
                    <input type="search" class="field pl-11" x-model="query" @input="onInput()"
                           placeholder="Search ticker or name — e.g. NVDA" autofocus>
                </div>
                <div class="mt-2 space-y-1.5" x-show="open" x-cloak>
                    <div x-show="loading" class="px-1 py-2 text-xs text-muted">Searching…</div>
                    <template x-for="row in results" :key="row.type + ':' + row.symbol">
                        <button type="button" @click="choose(row)"
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
                    <p x-show="!loading && query.length > 0 && results.length === 0" class="px-1 py-2 text-xs text-muted">No matches.</p>
                </div>
            </div>
            <p class="mt-2 text-xs text-muted">Any stock, ETF, index fund, commodity or crypto with a live price — doesn't need to be a position you already hold.</p>
        </div>

        <div x-show="asset" x-cloak class="flex items-center gap-3 rounded-2xl bg-ink-700 p-3">
            <span class="logo-bubble">
                <template x-if="asset && asset.logo_url"><img :src="asset.logo_url" class="h-full w-full object-cover" alt=""></template>
                <template x-if="asset && !asset.logo_url"><span x-text="asset && asset.symbol.slice(0,3)"></span></template>
            </span>
            <div class="min-w-0 flex-1">
                <div class="truncate font-semibold" x-text="asset && asset.name"></div>
                <div class="text-xs text-muted"><span x-text="asset && asset.symbol"></span> · <span x-text="asset && asset.type"></span></div>
            </div>
            <button type="button" class="text-sm text-muted hover:text-white" @click="asset = null">Change</button>
        </div>

        {{-- Condition --}}
        <div x-show="asset" x-cloak>
            <label class="mb-1.5 block text-sm font-semibold text-muted">Notify me when the price is…</label>
            <div class="flex gap-2">
                <button type="button" @click="condition = 'above'" class="tab flex-1" :class="condition === 'above' ? 'tab-active' : ''">Above</button>
                <button type="button" @click="condition = 'below'" class="tab flex-1" :class="condition === 'below' ? 'tab-active' : ''">Below</button>
            </div>
        </div>

        <div x-show="asset" x-cloak>
            <label class="mb-1.5 block text-sm font-semibold text-muted">
                Target price <span class="text-muted/60" x-text="asset ? '(' + (asset.currency || 'USD') + ')' : ''"></span>
            </label>
            <input type="number" step="any" min="0" class="field" x-model="targetPrice" inputmode="decimal" placeholder="0.00">
        </div>

        <p class="text-xs text-muted" x-show="asset" x-cloak>
            Checked every time prices refresh (about once an hour). You'll be notified once via
            <a href="{{ route('profile.edit') }}" class="underline">whatever channels you've connected</a> — the
            alert then deactivates; re-arm or delete it from the Alerts list.
        </p>

        <input type="hidden" name="type" :value="asset && asset.type">
        <input type="hidden" name="symbol" :value="asset && asset.symbol">
        <input type="hidden" name="name" :value="asset && asset.name">
        <input type="hidden" name="currency" :value="asset && asset.currency">
        <input type="hidden" name="provider_id" :value="asset && asset.provider_id">
        <input type="hidden" name="exchange" :value="asset && asset.exchange">
        <input type="hidden" name="logo_url" :value="asset && asset.logo_url">
        <input type="hidden" name="condition" :value="condition">
        <input type="hidden" name="target_price" :value="targetPrice">

        <button class="btn-primary w-full" x-show="asset" x-cloak
                :disabled="!targetPrice || Number(targetPrice) <= 0"
                :class="(!targetPrice || Number(targetPrice) <= 0) ? 'opacity-40 pointer-events-none' : ''">
            Create alert
        </button>
    </form>
  </div>
</x-layouts.mobile>
