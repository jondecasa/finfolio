@props([
    'series' => [],
    'ranges' => [],
    'activeRange' => '1W',
    'accountId' => null,
    'height' => 240,
    'lgHeight' => null,
])

<div
    x-data="netWorthChart({
        range: @js($activeRange),
        payload: @js($series),
        endpoint: '{{ route('api.series') }}',
        accountId: {{ $accountId ? (int) $accountId : 'null' }},
    })"
    class="w-full"
>
    <div class="chart-box relative app-pad" style="--h: {{ $height }}px; --h-lg: {{ $lgHeight ?? $height }}px">
        <canvas x-ref="canvas"></canvas>
        <div x-show="loading" x-cloak class="absolute inset-0 flex items-center justify-center">
            <div class="h-6 w-6 animate-spin rounded-full border-2 border-white/20 border-t-white"></div>
        </div>
    </div>

    <div class="app-pad mt-3 flex items-center gap-1 text-[11px] text-muted/70">
        <span>Chart by</span><span class="font-semibold text-muted">Finfolio</span>
    </div>

    <div class="no-scrollbar mt-2 flex gap-1 overflow-x-auto app-pad">
        @foreach ($ranges as $key => $range)
            <button
                type="button"
                @click="select('{{ $key }}')"
                class="pill"
                :class="range === '{{ $key }}' ? 'pill-active' : ''"
            >{{ $range['label'] }}</button>
        @endforeach
    </div>
</div>
