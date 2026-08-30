@props([
    'amount' => 0,
    'currency' => 'EUR',
    'hidden' => false,
    'decimals' => 2,
])
<span {{ $attributes }}>{{ $hidden ? '••••••' : \App\Support\Money::format((float) $amount, $currency, $decimals) }}</span>
