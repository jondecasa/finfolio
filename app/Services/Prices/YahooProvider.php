<?php

namespace App\Services\Prices;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YahooProvider implements PriceProvider
{
    public function __construct(
        protected string $baseUrl,
    ) {}

    protected function request()
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 300)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122 Safari/537.36',
            ]);
    }

    public function quotes(array $assets): array
    {
        $out = [];

        foreach ($assets as $asset) {
            $symbol = strtoupper($asset->symbol);

            try {
                $data = $this->request()
                    ->get("/v8/finance/chart/{$asset->symbol}", [
                        'range' => '5d',
                        'interval' => '1d',
                    ])
                    ->throw()
                    ->json('chart.result.0');
            } catch (\Throwable $e) {
                Log::warning("Yahoo quote failed for {$symbol}: ".$e->getMessage());

                continue;
            }

            if (! $data) {
                continue;
            }

            $meta = $data['meta'] ?? [];
            $price = $meta['regularMarketPrice'] ?? null;
            $prevClose = $meta['chartPreviousClose'] ?? $meta['previousClose'] ?? null;

            $out[$symbol] = new Quote(
                symbol: $symbol,
                price: $price !== null ? (float) $price : null,
                previousClose: $prevClose !== null ? (float) $prevClose : null,
                changePct: ($price !== null && $prevClose)
                    ? ((float) $price - (float) $prevClose) / (float) $prevClose * 100
                    : null,
                name: $meta['longName'] ?? $meta['shortName'] ?? null,
                currency: $meta['currency'] ?? null,
                providerId: $meta['symbol'] ?? $asset->symbol,
                meta: [
                    'exchange' => $meta['fullExchangeName'] ?? $meta['exchangeName'] ?? null,
                    'instrument_type' => $meta['instrumentType'] ?? null,
                ],
            );
        }

        return $out;
    }

    /** Yahoo quoteType => Finfolio asset type */
    protected const TYPE_MAP = [
        'EQUITY' => 'stock',
        'ETF' => 'etf',
        'MUTUALFUND' => 'index',
        'INDEX' => 'index',
        'FUTURE' => 'commodity',
    ];

    public function search(string $query, ?string $type = null): array
    {
        try {
            $data = $this->request()->get('/v1/finance/search', [
                'q' => $query,
                'quotesCount' => 20,
                'newsCount' => 0,
            ])->throw()->json();
        } catch (\Throwable $e) {
            Log::warning('Yahoo search failed: '.$e->getMessage());

            return [];
        }

        return collect($data['quotes'] ?? [])
            ->map(function ($q) {
                $mapped = self::TYPE_MAP[$q['quoteType'] ?? ''] ?? null;
                if (empty($q['symbol']) || $mapped === null) {
                    return null;
                }

                return [
                    'symbol' => strtoupper($q['symbol']),
                    'name' => $q['longname'] ?? $q['shortname'] ?? $q['symbol'],
                    'type' => $mapped,
                    'exchange' => $q['exchDisp'] ?? $q['exchange'] ?? null,
                    'currency' => null,
                    'provider_id' => $q['symbol'],
                    'logo_url' => null,
                ];
            })
            ->filter()
            ->when($type, fn ($c) => $c->where('type', $type))
            ->values()
            ->all();
    }
}
