<?php

namespace App\Services;

use App\Models\Asset;
use App\Services\Prices\CoinGeckoProvider;
use App\Services\Prices\PriceProvider;
use App\Services\Prices\YahooProvider;
use Illuminate\Support\Collection;

class PriceService
{
    /** @var array<string, PriceProvider> */
    protected array $resolved = [];

    public function provider(string $name): PriceProvider
    {
        return $this->resolved[$name] ??= match ($name) {
            'coingecko' => new CoinGeckoProvider(
                config('finfolio.coingecko.base_url'),
                config('finfolio.coingecko.api_key'),
            ),
            'yahoo' => new YahooProvider(
                config('finfolio.yahoo.base_url'),
            ),
            default => throw new \InvalidArgumentException("Unknown price provider [{$name}]"),
        };
    }

    public function providerForType(string $type): PriceProvider
    {
        $name = config("finfolio.providers.$type", config('finfolio.providers.stock'));

        return $this->provider($name);
    }

    /**
     * Refresh live prices for a set of assets, grouped by type/provider.
     *
     * @param  iterable<Asset>  $assets
     * @return int number of assets updated
     */
    public function refresh(iterable $assets): int
    {
        $assets = Collection::make($assets)->filter(fn (Asset $a) => in_array($a->type, Asset::PRICED_TYPES, true));
        $updated = 0;

        foreach ($assets->groupBy('type') as $type => $group) {

            $provider = $this->providerForType($type);
            $quotes = $provider->quotes($group->all());

            foreach ($group as $asset) {
                $quote = $quotes[strtoupper($asset->symbol)] ?? null;
                if (! $quote || $quote->price === null) {
                    continue;
                }

                $asset->fill([
                    'current_price' => $quote->price,
                    'previous_close' => $quote->previousClose,
                    'change_pct' => $quote->resolvedChangePct(),
                    'price_updated_at' => now(),
                ]);

                // The provider is authoritative for the trading currency of a
                // priced asset (e.g. a German stock quoted in EUR).
                if ($quote->currency) {
                    $asset->currency = strtoupper($quote->currency);
                }
                if ($quote->providerId && ! $asset->provider_id) {
                    $asset->provider_id = $quote->providerId;
                }
                if ($quote->name && (! $asset->name || $asset->name === $asset->symbol)) {
                    $asset->name = $quote->name;
                }
                if ($quote->logoUrl && ! $asset->logo_url) {
                    $asset->logo_url = $quote->logoUrl;
                }

                $asset->save();
                $updated++;
            }
        }

        return $updated;
    }

    public function refreshAll(): int
    {
        return $this->refresh(Asset::query()->whereIn('type', Asset::PRICED_TYPES)->get());
    }

    /**
     * Search across every configured provider.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, ?string $type = null): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 1) {
            return [];
        }

        // Manually-valued types (real estate, other…) have nothing to search for.
        if (in_array($type, Asset::MANUAL_TYPES, true)) {
            return [];
        }

        // The picked category chooses the provider; it does NOT filter the
        // provider's own classification. A UCITS ETF searched under "Index
        // funds" is still worth showing (Yahoo may label it ETF / MUTUALFUND /
        // INDEX inconsistently, and ISIN lookups resolve to whatever listing).
        $providers = [];
        if ($type === 'crypto') {
            $providers[] = [$this->provider(config('finfolio.providers.crypto')), null];
        } elseif (in_array($type, ['stock', 'etf', 'index', 'fund', 'commodity'], true)) {
            $providers[] = [$this->provider(config('finfolio.providers.stock')), null];
        } else {
            $providers[] = [$this->provider(config('finfolio.providers.crypto')), null];
            $providers[] = [$this->provider(config('finfolio.providers.stock')), null];
        }

        $results = [];
        foreach ($providers as [$provider, $providerType]) {
            foreach ($provider->search($query, $providerType) as $row) {
                $results[$row['type'].':'.$row['symbol']] = $row;
            }
        }

        return array_values($results);
    }

    /**
     * Find or create the asset "label" for a holding. Priced types are then
     * priced from their provider; manual types (real estate, cash, other) carry
     * their value on the holding row instead, so nothing is priced here.
     *
     * @param  array<string, mixed>  $row
     */
    public function resolveAsset(array $row): Asset
    {
        $asset = Asset::firstOrNew([
            'type' => $row['type'],
            'symbol' => strtoupper($row['symbol']),
        ]);

        // Placeholder currency; for priced assets refresh() will replace it with
        // the provider's trading currency.
        $currency = $asset->currency
            ?: (! empty($row['currency']) ? strtoupper($row['currency']) : 'USD');

        $asset->fill([
            'name' => $row['name'] ?? $asset->name ?? strtoupper($row['symbol']),
            'exchange' => $row['exchange'] ?? $asset->exchange,
            'currency' => $currency,
            'provider_id' => $asset->provider_id ?: ($row['provider_id'] ?? null),
            'logo_url' => $asset->logo_url ?: ($row['logo_url'] ?? null),
        ]);

        $asset->save();

        if (in_array($asset->type, Asset::PRICED_TYPES, true)) {
            $this->refresh([$asset->fresh()]);
        }

        return $asset->fresh();
    }
}
