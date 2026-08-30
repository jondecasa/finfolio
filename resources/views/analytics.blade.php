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

    $positionSegments = $buildSegments($positions, 'symbol');
    $typeSegments = $buildSegments($allocation['by_type'], 'label');
@endphp

<x-layouts.mobile heading="Allocation" title="Finfolio · Allocation">
    <div class="lg:mx-auto lg:max-w-3xl"
         x-data="{
             tab: @js($tab),
             currency: @js($currency),
             chart: null,
             hovered: null,
             pinned: null,
             data: { positions: @js($positionSegments), type: @js($typeSegments) },
             counts: { positions: {{ $positions->count() }}, type: {{ $allocation['by_type']->count() }} },
             get segments() { return this.data[this.tab] || []; },
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
        <div class="no-scrollbar app-pad flex gap-2 overflow-x-auto pb-2">
            @foreach (['positions' => 'All positions', 'type' => 'Type'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'" class="tab" :class="tab === '{{ $key }}' ? 'tab-active' : ''">{{ $label }}</button>
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
                    <span class="text-muted">Total value</span>
                    <x-money :amount="$allocation['total']" :currency="$currency" :hidden="$hidden" class="font-semibold" />
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

        {{-- All positions --}}
        <div class="app-pad mt-6 space-y-2" x-show="tab === 'positions'">
            @forelse ($positions as $i => $p)
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
                        <div class="mt-0.5 flex items-center justify-between gap-2 text-xs text-muted">
                            <span>
                                <x-money :amount="$p['value']" :currency="$currency" :hidden="$hidden" />
                            </span>
                            @if ($p['day_change_pct'] !== null)
                                <x-change :pct="$p['day_change_pct']" />
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <p class="py-10 text-center text-sm text-muted">No positions yet.</p>
            @endforelse
        </div>

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
