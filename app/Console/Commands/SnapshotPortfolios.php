<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PortfolioService;
use Illuminate\Console\Command;

class SnapshotPortfolios extends Command
{
    protected $signature = 'portfolio:snapshot {--refresh : Refresh live prices first}';

    protected $description = 'Store a net-worth snapshot for every user';

    public function handle(PortfolioService $portfolio): int
    {
        if ($this->option('refresh')) {
            $this->call('prices:refresh');
        }

        $count = 0;

        User::query()->each(function (User $user) use ($portfolio, &$count) {
            $portfolio->snapshot($user);
            $count++;
        });

        $this->info("Snapshotted {$count} portfolios.");

        return self::SUCCESS;
    }
}
