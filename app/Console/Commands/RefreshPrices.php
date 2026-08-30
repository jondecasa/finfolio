<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\PriceService;
use Illuminate\Console\Command;

class RefreshPrices extends Command
{
    protected $signature = 'prices:refresh {--type= : Only refresh a single asset type}';

    protected $description = 'Fetch live prices for every tracked asset';

    public function handle(PriceService $prices): int
    {
        $query = Asset::query()->where('type', '!=', 'cash');

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        $assets = $query->get();

        if ($assets->isEmpty()) {
            $this->info('No assets to refresh.');

            return self::SUCCESS;
        }

        $updated = $prices->refresh($assets);

        $this->info("Refreshed {$updated}/{$assets->count()} assets.");

        return self::SUCCESS;
    }
}
