@php
    $firstCategory = array_key_first($categories) ?? 'stock';
@endphp

<x-layouts.mobile heading="Search" title="Finfolio · Search">
    <div class="app-pad lg:mx-auto lg:max-w-2xl"
         x-data="{
             category: @js($firstCategory),
             query: '',
             results: [],
             loading: false,
             timer: null,
             setCategory(c) {
                 this.category = c;
                 this.results = [];
                 if (this.query.trim()) this.run();
             },
             onInput() {
                 clearTimeout(this.timer);
                 if (this.query.trim().length < 1) { this.results = []; return; }
                 this.timer = setTimeout(() => this.run(), 280);
             },
             async run() {
                 this.loading = true;
                 try {
                     const url = new URL('{{ route('api.search') }}', window.location.origin);
                     url.searchParams.set('q', this.query.trim());
                     url.searchParams.set('type', this.category);
                     const res = await fetch(url, { headers: { Accept: 'application/json' } });
                     this.results = (await res.json()).results || [];
                 } catch (e) { this.results = []; }
                 finally { this.loading = false; }
             },
             addLink(row) {
                 const p = new URLSearchParams({
                     type: row.type, symbol: row.symbol, name: row.name,
                     provider_id: row.provider_id || '', exchange: row.exchange || '',
                     currency: row.currency || '', logo_url: row.logo_url || ''
                 });
                 return '{{ route('holdings.create') }}?' + p.toString();
             },
         }">

        {{-- Category selector --}}
        <div class="flex flex-wrap gap-2">
            @foreach ($categories as $key => $cat)
                <button type="button" @click="setCategory('{{ $key }}')" class="tab"
                        :class="category === '{{ $key }}' ? 'tab-active' : ''">{{ $cat['label'] }}</button>
            @endforeach
        </div>

        {{-- Search box --}}
        <div class="mt-4">
            <div class="relative">
                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m21 21-4-4"/></svg>
                <input type="search" class="field pl-11" x-model="query" @input="onInput()"
                       :placeholder="'Search ' + category + '…'" autofocus>
            </div>
        </div>

        {{-- Results --}}
        <div class="mt-4 space-y-2">
            <div x-show="loading" class="flex justify-center py-8">
                <div class="h-6 w-6 animate-spin rounded-full border-2 border-white/20 border-t-white"></div>
            </div>

            <template x-for="row in results" :key="row.type + ':' + row.symbol">
                <a :href="addLink(row)" class="flex items-center gap-3 rounded-2xl bg-ink-800 p-3 hover:bg-ink-700">
                    <span class="logo-bubble">
                        <template x-if="row.logo_url"><img :src="row.logo_url" class="h-full w-full object-cover" alt=""></template>
                        <template x-if="!row.logo_url"><span x-text="row.symbol.slice(0,3)"></span></template>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-semibold" x-text="row.name"></div>
                        <div class="text-xs text-muted">
                            <span x-text="row.symbol"></span>
                            <span x-show="row.exchange"> · <span x-text="row.exchange"></span></span>
                        </div>
                    </div>
                    <span class="chip uppercase" x-text="row.type"></span>
                </a>
            </template>

            <template x-if="!loading && query.length > 0 && results.length === 0">
                <p class="py-10 text-center text-sm text-muted">No matches for "<span x-text="query"></span>".</p>
            </template>
            <template x-if="query.length === 0">
                <p class="py-10 text-center text-sm text-muted">Pick a category, then search by name or ticker.</p>
            </template>
        </div>

        <p class="mt-6 text-center text-xs text-muted/70">
            Cash and real estate are added from
            <a href="{{ route('holdings.create') }}" class="text-muted underline">Add position</a>.
        </p>
    </div>
</x-layouts.mobile>
