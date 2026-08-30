<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <meta name="robots" content="noindex">
    <title>Offline · Finfolio</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-ink font-sans text-white">
    <div class="mx-auto flex min-h-screen w-full max-w-sm flex-col items-center justify-center px-6 text-center">
        <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-2xl font-black text-ink">₣</div>
        <h1 class="text-xl font-bold">You're offline</h1>
        <p class="mt-2 text-sm text-muted">Finfolio needs a connection to show live prices. Check your network and try again.</p>
        <button onclick="location.reload()" class="btn-primary mt-6">Retry</button>
    </div>
</body>
</html>
