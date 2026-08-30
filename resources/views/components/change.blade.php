@props([
    'value' => null,
    'pct' => null,
    'currency' => 'EUR',
    'hidden' => false,
    'arrow' => true,
])
@php
    $basis = $pct ?? $value ?? 0;
    $up = $basis >= 0;
    $pctText = $pct !== null ? number_format(abs($pct), 2) . '%' : null;
    $valText = $value !== null ? \App\Support\Money::format(abs((float) $value), $currency) : null;
@endphp
<span {{ $attributes->merge(['class' => ($up ? 'value-up' : 'value-down') . ' inline-flex items-center gap-1 font-semibold']) }}>
    @if ($arrow)
        <svg class="h-3.5 w-3.5 {{ $up ? '' : 'rotate-180' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M7 17 17 7M9 7h8v8"/>
        </svg>
    @endif
    @if ($hidden)
        ••••
    @else
        <span>{{ $up ? '' : '−' }}{{ collect([$pctText, $valText])->filter()->implode('  ') }}</span>
    @endif
</span>
