@php
    $items = [
        ['route' => 'home',      'label' => 'Home',      'icon' => 'M3 11.5 12 4l9 7.5M5 10v10h14V10'],
        ['route' => 'networth',  'label' => 'Net Worth', 'icon' => 'M4 19V5m0 14h16M8 15l3-4 3 3 5-7'],
        ['route' => 'analytics', 'label' => 'Analytics', 'icon' => 'M12 3a9 9 0 1 0 9 9h-9V3Z'],
        ['route' => 'wealth',    'label' => 'Wealth',    'icon' => 'M4 18 10 12l4 3 6-8M4 6v12'],
        ['route' => 'search',    'label' => 'Search',    'icon' => 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Zm10 17-5-5'],
    ];
@endphp
<aside class="side-nav">
    <a href="{{ route('home') }}" class="mb-6 flex items-center gap-2.5 px-3">
        <x-application-logo class="h-9 w-9 rounded-xl" />
        <span class="text-lg font-bold">Finfolio</span>
    </a>

    <nav class="flex flex-1 flex-col gap-1">
        @foreach ($items as $item)
            <a href="{{ route($item['route']) }}" @class(['side-nav-link', 'active' => request()->routeIs($item['route'])])>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="{{ $item['icon'] }}"/>
                </svg>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="mt-4 space-y-2 border-t border-white/5 pt-4">
        <a href="{{ route('holdings.create') }}" class="btn-primary w-full !py-2.5">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
            Add position
        </a>
        <a href="{{ route('accounts.index') }}" @class(['side-nav-link', 'active' => request()->routeIs('accounts.*')])>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/></svg>
            Accounts
        </a>
        <a href="{{ route('profile.edit') }}" @class(['side-nav-link', 'active' => request()->routeIs('profile.*')])>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.2"/><path d="M5 20c1.5-3.5 4-5 7-5s5.5 1.5 7 5"/></svg>
            {{ \Illuminate\Support\Str::of(auth()->user()->name ?? 'Account')->limit(18) }}
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="side-nav-link w-full">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                Log out
            </button>
        </form>
    </div>
</aside>
