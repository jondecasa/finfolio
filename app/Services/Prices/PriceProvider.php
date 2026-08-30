<?php

namespace App\Services\Prices;

use App\Models\Asset;

interface PriceProvider
{
    /**
     * Fetch live quotes for the given assets.
     *
     * @param  array<int, Asset>  $assets
     * @return array<string, Quote> keyed by asset symbol (upper-case)
     */
    public function quotes(array $assets): array;

    /**
     * Search the provider catalogue for assets a user could add.
     *
     * @param  string|null  $type  restrict results to one Finfolio asset type
     * @return array<int, array{symbol:string,name:string,type:string,exchange:?string,currency:?string,provider_id:?string,logo_url:?string}>
     */
    public function search(string $query, ?string $type = null): array;
}
