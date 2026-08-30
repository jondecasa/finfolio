@php
    $currency = $summary['currency'];
    $hidden = auth()->user()->values_hidden;

    // Asset types present in the current holdings, ordered like the category list.
    $order = array_keys(config('finfolio.categories', []));
    $presentTypes = $positions
        ->map(fn ($p) => $p['holding']->displayType())
        ->unique()
        ->sortBy(fn ($t) => array_search($t, $order) === false ? 99 : array_search($t, $order))
        ->values();
    $typeLabel = fn ($t) => config("finfolio.categories.$t.label", \Illuminate\Support\Str::headline($t));
    $allRowTypes = $positions->map(fn ($p) => $p['holding']->displayType())->values();
@endphp

<x-layouts.mobile heading="Wealth" title="Finfolio · Wealth" wide>
    <div class="app-pad">
        <div class="flex gap-6 border-b border-white/5 pb-2 text-lg font-bold">
            <a href="{{ route('networth') }}" class="text-muted hover:text-white">Overview</a>
            <span class="text-white">Investments</span>
        </div>

        {{-- Account filter --}}
        <div class="no-scrollbar -mx-5 mt-4 flex gap-2 overflow-x-auto px-5 lg:mx-0 lg:flex-wrap lg:px-0">
            <a href="{{ route('wealth') }}" class="tab shrink-0 {{ $account ? '' : 'tab-active' }}">All accounts</a>
            @foreach ($accounts as $acc)
                <a href="{{ route('wealth', ['account' => $acc->id]) }}"
                   class="tab shrink-0 {{ $account && $account->id === $acc->id ? 'tab-active' : '' }}">{{ $acc->name }}</a>
            @endforeach
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="card-tight">
                <div class="text-xs text-muted">Market value</div>
                <x-money :amount="$summary['total_value']" :currency="$currency" :hidden="$hidden" class="mt-1 block text-xl font-bold" />
            </div>
            <div class="card-tight">
                <div class="text-xs text-muted">Invested</div>
                <x-money :amount="$summary['total_invested']" :currency="$currency" :hidden="$hidden" class="mt-1 block text-xl font-bold" />
            </div>
            <div class="card-tight">
                <div class="text-xs text-muted">Total return</div>
                <x-change :value="$summary['total_gain']" :pct="$summary['total_gain_pct']" :currency="$currency" :hidden="$hidden" class="mt-1 text-lg" />
            </div>
            <div class="card-tight">
                <div class="text-xs text-muted">Today</div>
                <x-change :value="$summary['day_change']" :pct="$summary['day_change_pct']" :currency="$currency" :hidden="$hidden" class="mt-1 text-lg" />
            </div>
        </div>
    </div>

    <div class="app-pad mt-6"
         x-data="{
             types: [],
             rowTypes: @js($allRowTypes),
             toggle(t) { this.types = this.types.includes(t) ? this.types.filter(x => x !== t) : [...this.types, t]; },
             shows(t) { return this.types.length === 0 || this.types.includes(t); },
             get anyVisible() { return this.types.length === 0 || this.rowTypes.some(t => this.types.includes(t)); },
         }">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-bold">Holdings{{ $account ? ' · '.$account->name : '' }}</h2>
            <a href="{{ route('holdings.create', $account ? ['account' => $account->id] : []) }}" class="text-sm text-muted hover:text-white">+ Add</a>
        </div>

        {{-- Type filter --}}
        @if ($presentTypes->count() > 1)
            <div class="no-scrollbar -mx-5 mb-3 flex gap-2 overflow-x-auto px-5 lg:mx-0 lg:flex-wrap lg:px-0">
                <button type="button" @click="types = []" class="tab shrink-0" :class="types.length === 0 ? 'tab-active' : ''">All types</button>
                @foreach ($presentTypes as $t)
                    <button type="button" @click="toggle('{{ $t }}')" class="tab shrink-0"
                            :class="types.includes('{{ $t }}') ? 'tab-active' : ''">{{ $typeLabel($t) }}</button>
                @endforeach
            </div>
        @endif

        <div class="card divide-y divide-white/5">
            @forelse ($positions as $p)
                @php $h = $p['holding']; @endphp
                <a href="{{ route('holdings.edit', $h) }}" class="row-link"
                   x-show="shows('{{ $h->displayType() }}')">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="logo-bubble">
                            @if ($h->asset->logo_url)
                                <img src="{{ $h->asset->logo_url }}" alt="" class="h-full w-full object-cover">
                            @else
                                {{ \Illuminate\Support\Str::substr($h->asset->symbol, 0, 3) }}
                            @endif
                        </span>
                        <div class="min-w-0">
                            <div class="truncate font-semibold">{{ $h->asset->name }}</div>
                            <div class="text-xs text-muted">
                                {{ rtrim(rtrim(number_format($h->quantity, 6), '0'), '.') }} {{ $h->asset->symbol }}
                                @unless ($account) · {{ $h->account->name }} @endunless
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <x-money :amount="$p['value']" :currency="$currency" :hidden="$hidden" class="block font-semibold" />
                        @if ($p['gain_pct'] !== null)
                            <x-change :pct="$p['gain_pct']" class="text-xs" />
                        @endif
                    </div>
                </a>
            @empty
                <p class="py-8 text-center text-sm text-muted">
                    {{ $account ? 'No holdings in this account yet.' : 'No holdings yet.' }}
                </p>
            @endforelse

            @if ($positions->isNotEmpty())
                <p x-show="!anyVisible" x-cloak class="py-8 text-center text-sm text-muted">No holdings of the selected type.</p>
            @endif
        </div>
    </div>
</x-layouts.mobile>
