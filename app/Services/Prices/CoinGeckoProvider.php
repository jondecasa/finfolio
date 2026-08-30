<?php

namespace App\Services\Prices;

use App\Models\Asset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoinGeckoProvider implements PriceProvider
{
    public function __construct(
        protected string $baseUrl,
        protected ?string $apiKey = null,
    ) {}

    protected function request()
    {
        $client = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 300);

        if ($this->apiKey) {
            $client = $client->withHeaders(['x-cg-demo-api-key' => $this->apiKey]);
        }

        return $client;
    }

    public function quotes(array $assets): array
    {
        if (empty($assets)) {
            return [];
        }

        // Resolve a coingecko id for every asset (symbol -> id) using the cached coin list.
        $map = $this->symbolToId();
        $ids = [];
        $idBySymbol = [];

        foreach ($assets as $asset) {
            $symbol = strtoupper($asset->symbol);
            $id = $asset->provider_id ?: ($map[$symbol] ?? null);

            if ($id) {
                $ids[] = $id;
                $idBySymbol[$symbol] = $id;
            }
        }

        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            return [];
        }

        try {
            $rows = $this->request()->get('/coins/markets', [
                'vs_currency' => 'usd',
                'ids' => implode(',', $ids),
                'price_change_percentage' => '24h',
                'per_page' => count($ids),
            ])->throw()->json() ?? [];
        } catch (\Throwable $e) {
            Log::warning('CoinGecko quotes failed: '.$e->getMessage());

            return [];
        }

        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['id']] = $row;
        }

        $out = [];
        foreach ($idBySymbol as $symbol => $id) {
            $row = $byId[$id] ?? null;
            if (! $row) {
                continue;
            }

            $out[$symbol] = new Quote(
                symbol: $symbol,
                price: isset($row['current_price']) ? (float) $row['current_price'] : null,
                previousClose: isset($row['current_price'], $row['price_change_24h'])
                    ? (float) $row['current_price'] - (float) $row['price_change_24h']
                    : null,
                changePct: isset($row['price_change_percentage_24h'])
                    ? (float) $row['price_change_percentage_24h']
                    : null,
                name: $row['name'] ?? null,
                currency: 'USD',
                providerId: $id,
                logoUrl: $row['image'] ?? null,
                meta: [
                    'market_cap' => $row['market_cap'] ?? null,
                    'market_cap_rank' => $row['market_cap_rank'] ?? null,
                ],
            );
        }

        return $out;
    }

    public function search(string $query, ?string $type = null): array
    {
        try {
            $data = $this->request()->get('/search', ['query' => $query])->throw()->json();
        } catch (\Throwable $e) {
            Log::warning('CoinGecko search failed: '.$e->getMessage());

            return [];
        }

        return collect($data['coins'] ?? [])
            ->take(15)
            ->map(fn ($coin) => [
                'symbol' => strtoupper($coin['symbol'] ?? ''),
                'name' => $coin['name'] ?? '',
                'type' => 'crypto',
                'exchange' => null,
                'currency' => 'USD',
                'provider_id' => $coin['id'] ?? null,
                'logo_url' => $coin['large'] ?? $coin['thumb'] ?? null,
            ])
            ->filter(fn ($row) => $row['symbol'] !== '')
            ->values()
            ->all();
    }

    /**
     * Cached symbol -> coingecko id lookup (first / most common match wins).
     *
     * @return array<string, string>
     */
    protected function symbolToId(): array
    {
        return Cache::remember('coingecko:symbol-id-map', now()->addHours(24), function () {
            try {
                $list = $this->request()->get('/coins/list')->throw()->json() ?? [];
            } catch (\Throwable $e) {
                Log::warning('CoinGecko coin list failed: '.$e->getMessage());

                return [];
            }

            $map = [];
            // Prefer well-known ids for ambiguous tickers.
            $preferred = [
                'BTC' => 'bitcoin', 'ETH' => 'ethereum', 'USDT' => 'tether',
                'BNB' => 'binancecoin', 'SOL' => 'solana', 'XRP' => 'ripple',
                'USDC' => 'usd-coin', 'ADA' => 'cardano', 'DOGE' => 'dogecoin',
                'AVAX' => 'avalanche-2', 'DOT' => 'polkadot', 'MATIC' => 'matic-network',
                'LINK' => 'chainlink', 'LTC' => 'litecoin', 'TRX' => 'tron',
            ];

            foreach ($list as $coin) {
                $sym = strtoupper($coin['symbol'] ?? '');
                if ($sym === '' || isset($map[$sym])) {
                    continue;
                }
                $map[$sym] = $coin['id'];
            }

            return array_merge($map, $preferred);
        });
    }
}
