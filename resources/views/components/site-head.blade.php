@props([
    'title' => 'Finfolio',
    'description' => 'Track your investment portfolio in real time: stocks, ETFs, index funds, commodities, crypto, real estate and cash — with live prices and net-worth history.',
    'robots' => 'index, follow',
])
@php
    $fullTitle = $title === 'Finfolio' ? 'Finfolio — Portfolio Tracker' : $title;
    $canonical = url()->current();
    $ogImage = asset('og-image.png');
@endphp

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonical }}">
<meta name="theme-color" content="#000000">
<meta name="color-scheme" content="dark">
<meta name="application-name" content="Finfolio">

{{-- Open Graph --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="Finfolio">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Finfolio — Portfolio Tracker">
<meta property="og:locale" content="es_ES">

{{-- Twitter --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $ogImage }}">

{{-- Icons + PWA --}}
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Finfolio">
