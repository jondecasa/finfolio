@php
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

<x-layouts.mobile heading="Positions" title="Finfolio · Positions" wide>
    <div class="app-pad">
        {{-- Account filter --}}
        <div class="no-scrollbar -mx-5 flex gap-2 overflow-x-auto px-5 lg:mx-0 lg:flex-wrap lg:px-0">
            <a href="{{ route('positions') }}" class="tab shrink-0 {{ $account ? '' : 'tab-active' }}">All accounts</a>
            @foreach ($accounts as $acc)
                <a href="{{ route('positions', ['account' => $acc->id]) }}"
                   class="tab shrink-0 {{ $account && $account->id === $acc->id ? 'tab-active' : '' }}">{{ $acc->name }}</a>
            @endforeach
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
