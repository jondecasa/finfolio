<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Holding;
use App\Models\PortfolioSnapshot;
use App\Models\User;
use App\Services\PortfolioService;
use App\Services\PriceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@finfolio.test'],
            [
                'name' => 'Demo Investor',
                'password' => Hash::make('password'),
                'base_currency' => 'EUR',
                'email_verified_at' => now(),
            ],
        );

        // Reset demo portfolio so the seeder is repeatable.
        PortfolioSnapshot::where('user_id', $user->id)->delete();
        Holding::whereHas('account', fn ($q) => $q->where('user_id', $user->id))->delete();
        Account::where('user_id', $user->id)->delete();

        $individual = $user->accounts()->create([
            'name' => 'Individual', 'type' => 'individual', 'currency' => 'EUR', 'is_default' => true, 'sort' => 0,
        ]);
        $individual2 = $user->accounts()->create([
            'name' => 'Individual 2', 'type' => 'individual', 'currency' => 'EUR', 'sort' => 1,
        ]);

        // --- Assets (prices pre-filled so the app renders offline) ---
        $btc = Asset::updateOrCreate(
            ['type' => 'crypto', 'symbol' => 'BTC'],
            [
                'provider_id' => 'bitcoin',
                'name' => 'Bitcoin',
                'currency' => 'USD',
                'logo_url' => 'https://assets.coingecko.com/coins/images/1/large/bitcoin.png',
                'current_price' => 65000,
                'previous_close' => 63280,
                'change_pct' => 2.72,
                'price_updated_at' => now(),
            ],
        );

        $eth = Asset::updateOrCreate(
            ['type' => 'crypto', 'symbol' => 'ETH'],
            [
                'provider_id' => 'ethereum',
                'name' => 'Ethereum',
                'currency' => 'USD',
                'logo_url' => 'https://assets.coingecko.com/coins/images/279/large/ethereum.png',
                'current_price' => 3400,
                'previous_close' => 3177,
                'change_pct' => 7.02,
                'price_updated_at' => now(),
            ],
        );

        Holding::create([
            'account_id' => $individual->id,
            'asset_id' => $btc->id,
            'quantity' => 1.130,
            'average_cost' => 41800,
        ]);

        Holding::create([
            'account_id' => $individual2->id,
            'asset_id' => $eth->id,
            'quantity' => 7.7465,
            'average_cost' => 2360,
        ]);

        // Pull live prices so the seeded history converges on a realistic "today".
        // Falls back silently to the hard-coded prices above when offline.
        try {
            app(PriceService::class)->refresh([$btc->fresh(), $eth->fresh()]);
        } catch (\Throwable $e) {
            $this->command?->warn('Live price refresh skipped: '.$e->getMessage());
        }

        $this->seedHistory($user->fresh());

        $this->command?->info('Seeded demo@finfolio.test / password');
    }

    /**
     * Build a believable net-worth history so every chart range looks populated.
     */
    protected function seedHistory(User $user): void
    {
        /** @var PortfolioService $portfolio */
        $portfolio = app(PortfolioService::class);
        $overview = $portfolio->overview($user);

        $target = $overview['total_value']; // current EUR net worth
        $accounts = collect($overview['accounts']);

        $now = CarbonImmutable::now();
        $start = $now->subDays(210);
        $days = $start->diffInDays($now);

        // Random walk that lands on $target today, trending up from ~62% of it.
        mt_srand(20240830);
        $value = $target * 0.62;
        $rows = [];

        for ($i = 0; $i <= $days; $i++) {
            $date = $start->addDays($i);
            $progress = $i / max($days, 1);

            // deterministic drift toward target + noise
            $drift = ($target - $value) * (0.015 + 0.03 * $progress);
            $noise = $value * (mt_rand(-140, 150) / 10000);
            $value = max($value + $drift + $noise, $target * 0.4);

            // last week: gently climb the final ~1.5% up to the live number
            if ($i >= $days - 7) {
                $w = ($i - ($days - 7)) / 7;
                $target7 = $target * (0.985 + 0.015 * $w);
                $value = $target7 + $value * (mt_rand(-25, 25) / 10000);
            }
            if ($i >= $days - 2) {
                $value += ($target - $value) * 0.7;
            }

            $pointsForDay = $i >= $days - 7 ? 6 : 1; // denser near "now" for 1D/1W
            for ($p = 0; $p < $pointsForDay; $p++) {
                $ts = $pointsForDay === 1
                    ? $date->setTime(20, 0)
                    : $date->setTime((int) (4 + $p * 3), 0);

                if ($ts->greaterThan($now)) {
                    continue;
                }

                $intraNoise = $pointsForDay === 1 ? 0 : $value * (mt_rand(-60, 60) / 10000);
                $dayValue = round($value + $intraNoise, 2);

                $rows[] = [
                    'user_id' => $user->id,
                    'account_id' => null,
                    'value' => $dayValue,
                    'invested' => round($overview['total_invested'], 2),
                    'currency' => $overview['currency'],
                    'captured_at' => $ts,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Split across accounts by their current weight.
                foreach ($accounts as $row) {
                    $weight = $target > 0 ? $row['value'] / $target : 0;
                    $rows[] = [
                        'user_id' => $user->id,
                        'account_id' => $row['account']->id,
                        'value' => round($dayValue * $weight, 2),
                        'invested' => round($row['invested'], 2),
                        'currency' => $overview['currency'],
                        'captured_at' => $ts,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            PortfolioSnapshot::insert($chunk);
        }
    }
}
