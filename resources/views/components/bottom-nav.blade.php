@php
    $items = [
        ['route' => 'home',       'label' => 'Home',      'icon' => 'M3 11.5 12 4l9 7.5M5 10v10h14V10'],
        ['route' => 'analytics',  'label' => 'Analytics', 'icon' => 'M12 3a9 9 0 1 0 9 9h-9V3Z'],
        ['route' => 'positions',  'match' => ['positions', 'holdings.*'], 'label' => 'Positions', 'icon' => 'M4 18 10 12l4 3 6-8M4 6v12'],
        ['route' => 'plans.index', 'match' => 'plans.*', 'label' => 'Plans', 'icon' => 'M17 2l4 4-4 4M3 11v-1a4 4 0 0 1 4-4h14M7 22l-4-4 4-4M21 13v1a4 4 0 0 1-4 4H3'],
        ['route' => 'search',     'label' => 'Search',    'icon' => 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Zm10 17-5-5'],
    ];
@endphp
<nav class="bottom-nav">
    @foreach ($items as $item)
        <a href="{{ route($item['route']) }}" @class(['active' => request()->routeIs($item['match'] ?? $item['route'])])>
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="{{ $item['icon'] }}"/>
            </svg>
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>
