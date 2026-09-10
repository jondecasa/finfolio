@php
    $currency = $allocation['currency'];
    $hidden = auth()->user()->values_hidden;
    $positions = $allocation['positions'];
    $palette = ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#f43f5e', '#84cc16'];
    $tailColor = '#3f3f46';

    $buildSegments = function ($rows, $labelKey) use ($palette, $tailColor) {
        $rows = $rows->values();
        $head = $rows->take(7)->map(fn ($r, $i) => [
            'label' => $r[$labelKey],
            'weight' => $r['weight'],
            'value' => $r['value'],
            'color' => $palette[$i % count($palette)],
        ]);
        if ($rows->count() > 7) {
            $rest = $rows->slice(7);
            $head->push([
                'label' => 'Other',
                'weight' => $rest->sum('weight'),
                'value' => $rest->sum('value'),
                'color' => $tailColor,
            ]);
        }
        return $head->values();
    };

    $positionSegments = $buildSegments($positions, 'name');
    $typeSegments = $buildSegments($allocation['by_type'], 'label');

    // One extra hero-ring view per asset type present: shows how that type's
    // own positions are split among themselves (e.g. how 3 ETFs stack up).
    $presentTypes = $allocation['by_type'];
    $typeRings = [];
    $typeLists = [];
    foreach ($presentTypes as $t) {
        $rows = $positions->where('type', $t['key'])->sortByDesc('value')->values();
        $sub = $rows->sum('value');
        $rows = $rows->map(function ($p) use ($sub) {
            // Weight relative to this type's subtotal so the slice %s add to 100.
            $p['weight'] = $sub > 0 ? $p['value'] / $sub * 100 : 0;
            return $p;
        })->values();
        $typeRings[$t['key']] = $buildSegments($rows, 'name');
        $typeLists[$t['key']] = $rows;
    }

    // Position-list views keyed by tab: "positions" (all) plus one per type.
    $listViews = ['positions' => $positions->values()];
    foreach ($typeLists as $key => $rows) {
        $listViews[$key] = $rows;
    }

    // Second ring: money actually invested (equity put in — real estate's down
    // payment, everything else's cost basis; cash excluded), grouped by type,
    // as opposed to the hero ring above which is weighted by current value.
    $investedByType = $allocation['by_type']
        ->filter(fn ($t) => $t['invested'] > 0)
        ->map(fn ($t) => ['label' => $t['label'], 'value' => $t['invested'], 'weight' => $t['invested_weight']])
        ->sortByDesc('value')
        ->values();
    $investedSegments = $buildSegments($investedByType, 'label');
@endphp

<x-layouts.mobile heading="Analytics" title="Finfolio · Analytics">
    <div class="lg:mx-auto lg:max-w-3xl"
         x-data="{
             tab: @js($tab),
             currency: @js($currency),
             chart: null,
             hovered: null,
             pinned: null,
             data: {
                 positions: @js($positionSegments),
                 type: @js($typeSegments),
                 @foreach ($presentTypes as $t) {{ $t['key'] }}: @js($typeRings[$t['key']]), @endforeach
             },
             totals: {
                 positions: {{ $allocation['total'] }},
                 type: {{ $allocation['total'] }},
                 @foreach ($presentTypes as $t) {{ $t['key'] }}: {{ $typeLists[$t['key']]->sum('value') }}, @endforeach
             },
             labels: { @foreach ($presentTypes as $t) {{ $t['key'] }}: @js($t['label']), @endforeach },
             get segments() { return this.data[this.tab] || []; },
             get tabTotal() { return this.totals[this.tab] ?? this.totals.positions; },
             get totalLabel() { return this.labels[this.tab] ? this.labels[this.tab] + ' total' : 'Total value'; },
             get active() { return this.hovered || this.pinned; },
             get centerValue() { return this.active ? window.Finfolio.formatCurrency(this.active.value, this.currency) : ''; },
             get centerMeta() { return this.active ? this.active.label + ' · ' + this.active.weight.toFixed(1) + '%' : ''; },
             select(seg) {
                 this.hovered = seg;
             },
             pin(seg) {
                 this.pinned = (seg && this.pinned && this.pinned.label === seg.label) ? null : seg;
             },
             draw() {
                 if (this.chart) this.chart.destroy();
                 this.hovered = null;
                 this.pinned = null;
                 this.chart = window.Finfolio.ringChart(this.$refs.ring, {
                     segments: this.segments,
                     onHover: (seg) => this.select(seg),
                     onClick: (seg) => this.pin(seg),
                 });
             },
             init() {
                 this.draw();
                 this.$watch('tab', () => this.draw());
             },
         }">
        {{-- Account filter --}}
        <div class="no-scrollbar app-pad flex gap-2 overflow-x-auto pb-1 pt-1">
            <a href="{{ route('analytics', ['tab' => $tab]) }}" class="tab shrink-0 {{ $account ? '' : 'tab-active' }}">All accounts</a>
            @foreach ($accounts as $acc)
                <a href="{{ route('analytics', ['account' => $acc->id, 'tab' => $tab]) }}"
                   class="tab shrink-0 {{ $account && $account->id === $acc->id ? 'tab-active' : '' }}">{{ $acc->name }}</a>
            @endforeach
        </div>

        {{-- Headline stats --}}
        <div class="app-pad mt-3 grid grid-cols-2 gap-3 lg:grid-cols-3">
            <div class="card-tight">
                <div class="text-xs text-muted">Net value</div>
                <x-money :amount="$summary['net_value']" :currency="$currency" :hidden="$hidden" class="mt-1 block text-xl font-bold" />
            </div>
            <div class="card-tight">
                <div class="text-xs text-muted">Equity invested</div>
                <x-money :amount="$summary['equity_invested']" :currency="$currency" :hidden="$hidden" class="mt-1 block text-xl font-bold" />
            </div>
            <div class="card-tight">
                <div class="text-xs text-muted">Positions</div>
                <span class="mt-1 block text-xl font-bold">{{ $summary['positions_count'] }}</span>
            </div>
            <div class="card-tight">
                <div class="text-xs text-muted">Liabilities</div>
                @if ($summary['liabilities'] > 0)
                    <span class="value-down mt-1 block text-xl font-bold">− <x-money :amount="$summary['liabilities']" :currency="$currency" :hidden="$hidden" /></span>
                @else
                    <x-money :amount="0" :currency="$currency" :hidden="$hidden" class="mt-1 block text-xl font-bold" />
                @endif
            </div>
            <div class="card-tight">
                <div class="text-xs text-muted">Total return</div>
                <x-change :value="$summary['total_return']" :pct="$summary['total_return_pct']" :currency="$currency" :hidden="$hidden" class="mt-1 text-lg" />
            </div>
            <div class="card-tight">
                <div class="text-xs text-muted">Today</div>
                <x-change :value="$summary['day_change']" :pct="$summary['day_change_pct']" :currency="$currency" :hidden="$hidden" class="mt-1 text-lg" />
            </div>
        </div>

        <div class="no-scrollbar app-pad mt-5 flex gap-2 overflow-x-auto pb-2">
            <button type="button" @click="tab = 'positions'" class="tab shrink-0" :class="tab === 'positions' ? 'tab-active' : ''">All positions</button>
            <button type="button" @click="tab = 'type'" class="tab shrink-0" :class="tab === 'type' ? 'tab-active' : ''">Type</button>
            @foreach ($presentTypes as $t)
                <button type="button" @click="tab = '{{ $t['key'] }}'" class="tab shrink-0" :class="tab === '{{ $t['key'] }}' ? 'tab-active' : ''">{{ $t['label'] }}</button>
            @endforeach
        </div>

        {{-- Hero: allocation ring --}}
        <div class="app-pad mt-4">
            <div class="card">
                <div class="relative mx-auto" style="width: 220px; height: 220px;">
                    <canvas x-ref="ring"></canvas>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center px-8 text-center">
                        <div :class="{ 'hidden': !active }" class="transition-opacity duration-150">
                            <span class="block text-2xl font-bold leading-tight" x-text="centerValue"></span>
                            <span class="mt-1 block max-w-full truncate text-xs text-muted" x-text="centerMeta"></span>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex items-center justify-between border-t border-white/5 pt-4 text-sm">
                    <span class="text-muted" x-text="totalLabel"></span>
                    <span class="font-semibold" x-text="{{ $hidden ? "'••••••'" : 'window.Finfolio.formatCurrency(tabTotal, currency)' }}"></span>
                </div>

                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2">
                    <template x-for="s in segments" :key="s.label">
                        <button type="button"
                                @mouseenter="select(s)" @mouseleave="select(null)" @click="pin(s)"
                                class="inline-flex items-center gap-1.5 rounded-full px-1 text-xs transition"
                                :class="active && active.label === s.label ? 'bg-white/10' : ''">
                            <span class="h-2.5 w-2.5 rounded-full" :style="`background: ${s.color}`"></span>
                            <span class="font-semibold" x-text="s.label"></span>
                            <span class="text-muted" x-text="s.weight.toFixed(1) + '%'"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        {{-- Invested money (equity put in, by type) — a separate view from
             the hero ring, which is weighted by current value/net worth. --}}
        @if ($allocation['invested_total'] > 0)
            <div class="app-pad mt-4"
                 x-data="{
                     chart: null,
                     hovered: null,
                     pinned: null,
                     segments: @js($investedSegments),
                     currency: @js($currency),
                     get active() { return this.hovered || this.pinned; },
                     get centerValue() { return this.active ? window.Finfolio.formatCurrency(this.active.value, this.currency) : ''; },
                     get centerMeta() { return this.active ? this.active.label + ' · ' + this.active.weight.toFixed(1) + '%' : ''; },
                     select(seg) { this.hovered = seg; },
                     pin(seg) { this.pinned = (seg && this.pinned && this.pinned.label === seg.label) ? null : seg; },
                     init() {
                         this.chart = window.Finfolio.ringChart(this.$refs.ring2, {
                             segments: this.segments,
                             onHover: (seg) => this.select(seg),
                             onClick: (seg) => this.pin(seg),
                         });
                     },
                 }">
                <div class="card">
                    <h2 class="text-sm font-semibold text-muted">Invested money <span class="text-muted/60">(by type)</span></h2>
                    <p class="mt-0.5 text-xs text-muted/70">What you actually put in — not current value or net worth.</p>

                    <div class="relative mx-auto mt-2" style="width: 180px; height: 180px;">
                        <canvas x-ref="ring2"></canvas>
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center px-8 text-center">
                            <div :class="{ 'hidden': !active }" class="transition-opacity duration-150">
                                <span class="block text-xl font-bold leading-tight" x-text="centerValue"></span>
                                <span class="mt-1 block max-w-full truncate text-xs text-muted" x-text="centerMeta"></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex items-center justify-between border-t border-white/5 pt-4 text-sm">
                        <span class="text-muted">Total invested</span>
                        <x-money :amount="$allocation['invested_total']" :currency="$currency" :hidden="$hidden" class="font-semibold" />
                    </div>

                    <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2">
                        <template x-for="s in segments" :key="s.label">
                            <button type="button"
                                    @mouseenter="select(s)" @mouseleave="select(null)" @click="pin(s)"
                                    class="inline-flex items-center gap-1.5 rounded-full px-1 text-xs transition"
                                    :class="active && active.label === s.label ? 'bg-white/10' : ''">
                                <span class="h-2.5 w-2.5 rounded-full" :style="`background: ${s.color}`"></span>
                                <span class="font-semibold" x-text="s.label"></span>
                                <span class="text-muted" x-text="s.weight.toFixed(1) + '%'"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        @endif

        {{-- Positions list: all, or filtered to the selected type --}}
        @foreach ($listViews as $viewKey => $rows)
            <div class="app-pad mt-6 space-y-2" x-show="tab === '{{ $viewKey }}'" @if ($viewKey !== 'positions') x-cloak @endif>
                @forelse ($rows as $i => $p)
                    <a href="{{ route('holdings.edit', $p['holding']) }}" class="flex items-center gap-3 rounded-2xl bg-ink-800 p-3 transition hover:bg-ink-700">
                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $palette[$i % count($palette)] }}"></span>
                        <span class="logo-bubble">
                            @if ($p['logo_url'])
                                <img src="{{ $p['logo_url'] }}" alt="" class="h-full w-full object-cover">
                            @else
                                {{ \Illuminate\Support\Str::substr($p['symbol'], 0, 3) }}
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate font-semibold">{{ $p['name'] }}</span>
                                <span class="shrink-0 font-semibold">{{ number_format($p['weight'], 1) }}%</span>
                            </div>
                            <div class="mt-0.5 text-xs text-muted">
                                <x-money :amount="$p['value']" :currency="$currency" :hidden="$hidden" />
                            </div>
                        </div>
                    </a>
                @empty
                    <p class="py-10 text-center text-sm text-muted">No positions yet.</p>
                @endforelse
            </div>
        @endforeach

        {{-- By type --}}
        <div class="app-pad mt-6 space-y-2" x-show="tab === 'type'" x-cloak>
            @foreach ($allocation['by_type'] as $i => $t)
                <div class="flex items-center gap-3 rounded-2xl bg-ink-800 p-3">
                    <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $palette[$i % count($palette)] }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate font-semibold">{{ $t['label'] }}</span>
                            <span class="shrink-0 font-semibold">{{ number_format($t['weight'], 1) }}%</span>
                        </div>
                        <div class="mt-0.5 flex items-center justify-between gap-2 text-xs text-muted">
                            <span>{{ $t['count'] }} {{ \Illuminate\Support\Str::plural('position', $t['count']) }}</span>
                            <x-money :amount="$t['value']" :currency="$currency" :hidden="$hidden" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="app-pad mt-6 text-center text-[11px] text-muted/60">Crypto data from CoinGecko · Equity data from Yahoo Finance</p>
    </div>
</x-layouts.mobile>
