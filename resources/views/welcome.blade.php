<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Finfolio') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ink font-sans text-white">
    <div class="mx-auto flex min-h-screen max-w-app flex-col items-center justify-center px-6 text-center">
        <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-3xl font-black text-ink">₣</div>
        <h1 class="text-3xl font-bold">Finfolio</h1>
        <p class="mt-2 text-muted">Track every position, live.</p>
        <div class="mt-8 flex w-full flex-col gap-3">
            <a href="{{ route('login') }}" class="btn-primary w-full">Log in</a>
            <a href="{{ route('register') }}" class="btn-ghost w-full">Create account</a>
        </div>
    </div>
</body>
</html>
