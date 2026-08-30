<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FxService
{
    /** Reasonable offline fallbacks (per 1 USD). */
    protected array $fallback = [
        'USD' => 1.0,
        'EUR' => 0.92,
        'GBP' => 0.79,
        'CHF' => 0.88,
        'JPY' => 156.0,
        'CAD' => 1.37,
        'AUD' => 1.51,
        'GBp' => 79.0,
    ];

    public function convert(float $amount, string $from, string $to): float
    {
        $from = strtoupper($from ?: 'USD');
        $to = strtoupper($to ?: 'USD');

        if ($from === $to || $amount == 0.0) {
            return $amount;
        }

        $rates = $this->ratesInUsd();

        $fromRate = $rates[$from] ?? $this->fallback[$from] ?? null;
        $toRate = $rates[$to] ?? $this->fallback[$to] ?? null;

        if (! $fromRate || ! $toRate) {
            return $amount; // unknown currency: don't distort the number
        }

        $usd = $amount / $fromRate;

        return $usd * $toRate;
    }

    /**
     * Map of currency code => units per 1 USD.
     *
     * @return array<string, float>
     */
    public function ratesInUsd(): array
    {
        return Cache::remember('fx:usd-rates', config('finfolio.fx.cache_ttl'), function () {
            try {
                $data = Http::baseUrl(config('finfolio.fx.base_url'))
                    ->timeout(15)
                    ->get('/USD')
                    ->throw()
                    ->json();

                if (($data['result'] ?? null) === 'success' && ! empty($data['rates'])) {
                    return array_map('floatval', $data['rates']);
                }
            } catch (\Throwable $e) {
                Log::warning('FX rate fetch failed: '.$e->getMessage());
            }

            return $this->fallback;
        });
    }
}
