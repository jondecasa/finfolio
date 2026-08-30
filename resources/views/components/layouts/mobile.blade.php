@props([
    'heading' => 'Home',
    'title' => 'Finfolio',
    'back' => null,
    'wide' => false,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-site-head :title="$title ?? 'Finfolio'" robots="noindex, nofollow" />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ink">
    <x-side-nav />

    <div class="app-shell pb-28 lg:pb-12 lg:pl-64">
        <div class="mx-auto w-full {{ $wide ? 'lg:max-w-6xl' : 'lg:max-w-4xl' }} lg:px-8 lg:pt-6">
            {{-- Top bar --}}
            <header class="app-pad flex items-center justify-between pt-6 pb-4 lg:pt-2">
                <div class="flex items-center gap-3">
                    @isset($back)
                        <a href="{{ $back }}" class="text-2xl leading-none text-white/80 hover:text-white">&lsaquo;</a>
                    @else
                        <a href="{{ route('profile.edit') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-ink-700 text-white lg:hidden">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.2"/><path d="M5 20c1.5-3.5 4-5 7-5s5.5 1.5 7 5"/></svg>
                        </a>
                    @endisset
                    <h1 class="text-2xl font-bold lg:text-3xl">{{ $heading ?? 'Home' }}</h1>
                </div>
                <div class="flex items-center gap-3">
                    {{ $actions ?? '' }}
                    <form method="POST" action="{{ route('portfolio.refresh') }}">
                        @csrf
                        <button class="flex items-center gap-1.5 text-sm text-muted hover:text-white" title="Update prices">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12a8 8 0 0 1 13.7-5.7L20 8"/><path d="M20 4v4h-4"/><path d="M20 12a8 8 0 0 1-13.7 5.7L4 16"/><path d="M4 20v-4h4"/></svg>
                            <span>Update</span>
                        </button>
                    </form>
                </div>
            </header>

            @if (session('status'))
                <div class="app-pad mb-3">
                    <div class="rounded-2xl bg-gain/15 px-4 py-2.5 text-sm text-gain">{{ session('status') }}</div>
                </div>
            @endif
            @if ($errors->any())
                <div class="app-pad mb-3">
                    <div class="rounded-2xl bg-loss/15 px-4 py-2.5 text-sm text-loss">
                        {{ $errors->first() }}
                    </div>
                </div>
            @endif

            <main>
                {{ $slot }}
            </main>
        </div>
    </div>

    <a href="{{ route('holdings.create') }}"
       class="fixed bottom-24 right-5 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-white text-ink shadow-lg shadow-black/40 active:scale-95 lg:hidden">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
    </a>

    <x-bottom-nav />
</body>
</html>
