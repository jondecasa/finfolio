<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-site-head />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ink font-sans text-white antialiased">
    <div class="mx-auto flex min-h-screen w-full max-w-sm flex-col justify-center px-6 py-10">
        <div class="mb-8 text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-2xl font-black text-ink">₣</div>
            <h1 class="text-2xl font-bold">Finfolio</h1>
            <p class="mt-1 text-sm text-muted">Track every position, live.</p>
        </div>

        <div class="card">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
