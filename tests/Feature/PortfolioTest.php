<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Holding;
use App\Models\User;
use App\Services\PortfolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin FX so conversions are deterministic and offline.
        Http::fake([
            'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['USD' => 1.0, 'EUR' => 0.9]]),
            '*' => Http::response([], 200),
        ]);
    }

    protected function makePortfolio(): User
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR', 'is_default' => true]);

        $btc = Asset::create([
            'type' => 'crypto', 'symbol' => 'BTC', 'name' => 'Bitcoin', 'currency' => 'USD',
            'current_price' => 60000, 'previous_close' => 58000, 'change_pct' => 3.448,
            'price_updated_at' => now(),
        ]);
        $eth = Asset::create([
            'type' => 'crypto', 'symbol' => 'ETH', 'name' => 'Ethereum', 'currency' => 'USD',
            'current_price' => 3000, 'previous_close' => 3000, 'change_pct' => 0,
            'price_updated_at' => now(),
        ]);

        Holding::create(['account_id' => $account->id, 'asset_id' => $btc->id, 'quantity' => 1, 'average_cost' => 40000]);
        Holding::create(['account_id' => $account->id, 'asset_id' => $eth->id, 'quantity' => 5, 'average_cost' => 2000]);

        return $user;
    }

    public function test_overview_converts_and_aggregates_holdings(): void
    {
        $user = $this->makePortfolio();
        $overview = app(PortfolioService::class)->overview($user);

        // (1 * 60000 + 5 * 3000) USD = 75000 USD -> * 0.9 = 67500 EUR
        $this->assertEqualsWithDelta(67500, $overview['total_value'], 0.01);
        // invested: (40000 + 10000) USD * 0.9 = 45000 EUR
        $this->assertEqualsWithDelta(45000, $overview['total_invested'], 0.01);
        $this->assertEqualsWithDelta(22500, $overview['total_gain'], 0.01);
        $this->assertSame(2, $overview['positions_count']);
    }

    public function test_real_estate_debt_nets_off_net_worth_but_not_appreciation(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR']);

        $flat = Asset::create(['type' => 'realestate', 'symbol' => 'FLAT', 'name' => 'Flat', 'currency' => 'EUR']);
        Holding::create([
            'account_id' => $account->id,
            'asset_id' => $flat->id,
            'quantity' => 1,
            'average_cost' => 100,   // purchase price
            'manual_value' => 120,   // current value
            'debt' => 80,            // mortgage
        ]);

        $overview = app(PortfolioService::class)->overview($user);

        // Net worth = 120 current - 80 mortgage = 40
        $this->assertEqualsWithDelta(40, $overview['total_value'], 0.01);
        $this->assertEqualsWithDelta(80, $overview['total_debt'], 0.01);
        // Appreciation is 120 vs 100 = +20 (20%), debt aside.
        $this->assertEqualsWithDelta(20, $overview['total_gain'], 0.01);
        $this->assertEqualsWithDelta(20, $overview['total_gain_pct'], 0.01);
        $this->assertCount(1, $overview['debt_holdings']);
    }

    public function test_allocation_weights_sum_to_one_hundred(): void
    {
        $user = $this->makePortfolio();
        $allocation = app(PortfolioService::class)->allocation($user);

        $this->assertEqualsWithDelta(100, $allocation['positions']->sum('weight'), 0.001);
        $btc = $allocation['positions']->firstWhere('symbol', 'BTC');
        // 54000 / 67500 = 80%
        $this->assertEqualsWithDelta(80, $btc['weight'], 0.01);
    }

    public function test_home_screen_renders_for_authenticated_user(): void
    {
        $user = $this->makePortfolio();

        $this->actingAs($user)
            ->get('/home')
            ->assertOk()
            ->assertSee('Total Net Worth')
            ->assertSee('Accounts')
            ->assertSee('Cash balance')
            ->assertSee('Liabilities')
            ->assertDontSee('Allocation');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/home')->assertRedirect('/login');
    }

    public function test_analytics_screen_shows_stats_and_filters_by_account(): void
    {
        $user = $this->makePortfolio();
        $main = $user->accounts()->first();

        $this->actingAs($user)->get('/analytics')
            ->assertOk()
            ->assertSee('Net value')
            ->assertSee('Liabilities')
            ->assertSee('Total return')
            ->assertSee('Bitcoin');

        $this->actingAs($user)->get('/analytics?account='.$main->id)->assertOk()->assertSee('Bitcoin');

        $stranger = User::factory()->create();
        $strangerAccount = $stranger->accounts()->create(['name' => 'x', 'currency' => 'EUR']);
        $this->actingAs($user)->get('/analytics?account='.$strangerAccount->id)->assertNotFound();
    }

    public function test_analytics_total_return_is_net_of_debt(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR']);
        $flat = Asset::create(['type' => 'realestate', 'symbol' => 'FLAT', 'name' => 'Flat', 'currency' => 'EUR']);
        Holding::create([
            'account_id' => $account->id, 'asset_id' => $flat->id,
            'quantity' => 1, 'average_cost' => 100, 'manual_value' => 150, 'debt' => 40,
        ]);

        // Net value 150 − 40 = 110; invested 100 → total return +€10.00 (not the +€50 the gross gain would show).
        $this->actingAs($user)->get('/analytics')
            ->assertOk()
            ->assertSee('€10.00')
            ->assertDontSee('€50.00');
    }

    public function test_analytics_total_return_uses_equity_not_full_price_when_a_down_payment_is_set(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR']);
        $flat = Asset::create(['type' => 'realestate', 'symbol' => 'FLAT', 'name' => 'Flat', 'currency' => 'EUR']);
        Holding::create([
            'account_id' => $account->id, 'asset_id' => $flat->id,
            'quantity' => 1, 'average_cost' => 100, 'manual_value' => 150,
            'debt' => 40, 'mortgage_down_payment' => 10,
        ]);

        // Net value 150 − 40 = 110; equity invested is just the €10 down payment
        // (shown on its own tile) → total return is +€100.00 / +1,000%, not the
        // +€10.00 / +10% you'd get comparing net value to the full €100 price.
        $this->actingAs($user)->get('/analytics')
            ->assertOk()
            ->assertSee('€10.00')   // Equity invested tile
            ->assertSee('€100.00')  // Total return value
            ->assertSee('1,000.00%'); // Total return %
    }

    public function test_positions_screen_can_be_filtered_to_one_account(): void
    {
        $user = $this->makePortfolio();

        $other = $user->accounts()->create(['name' => 'Side pot', 'currency' => 'EUR']);
        $sol = Asset::create(['type' => 'crypto', 'symbol' => 'SOL', 'name' => 'Solana', 'currency' => 'USD', 'current_price' => 200]);
        Holding::create(['account_id' => $other->id, 'asset_id' => $sol->id, 'quantity' => 3, 'average_cost' => 100]);

        // Unfiltered shows everything.
        $this->actingAs($user)->get('/positions')
            ->assertOk()->assertSee('Bitcoin')->assertSee('Solana');

        // Filtered to the side account shows only its holding.
        $this->actingAs($user)->get('/positions?account='.$other->id)
            ->assertOk()->assertSee('Solana')->assertDontSee('Bitcoin');

        // Another user's account id is rejected.
        $stranger = User::factory()->create();
        $strangerAccount = $stranger->accounts()->create(['name' => 'x', 'currency' => 'EUR']);
        $this->actingAs($user)->get('/positions?account='.$strangerAccount->id)->assertNotFound();

        // Old URL still lands on the new screen.
        $this->actingAs($user)->get('/wealth')->assertRedirect('/positions');
    }

    public function test_series_endpoint_returns_points(): void
    {
        $user = $this->makePortfolio();
        app(PortfolioService::class)->snapshot($user);

        $this->actingAs($user)
            ->getJson('/api/series?range=1M')
            ->assertOk()
            ->assertJsonStructure(['currency', 'range', 'points', 'change', 'change_pct']);
    }

    public function test_gain_percent_uses_the_currency_the_user_paid_in(): void
    {
        // Asset trades in USD (e.g. IGLN.L on the LSE), but the user paid in EUR.
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR']);

        $gold = Asset::create([
            'type' => 'commodity', 'symbol' => 'IGLN.L', 'name' => 'iShares Physical Gold',
            'currency' => 'USD', 'current_price' => 100, 'previous_close' => 100,
            'price_updated_at' => now(),
        ]);

        $holding = Holding::create([
            'account_id' => $account->id,
            'asset_id' => $gold->id,
            'quantity' => 10,
            'average_cost' => 90,        // 90 per unit...
            'cost_currency' => 'EUR',    // ...paid in EUR, not the USD the asset trades in
        ]);

        $portfolio = app(PortfolioService::class);

        // Gross: 10 * 100 USD = 1000 USD -> * 0.9 = 900 EUR.
        // Invested: 10 * 90 EUR = 900 EUR (no conversion needed).
        // Return is therefore 0% — NOT +11% you'd get treating the cost as USD.
        $this->assertEqualsWithDelta(900, $portfolio->holdingInvested($holding, 'EUR'), 0.01);
        $this->assertEqualsWithDelta(0, $portfolio->holdingGainPct($holding, 'EUR'), 0.01);

        // Legacy row without cost_currency falls back to the asset's currency (USD).
        $holding->update(['cost_currency' => null]);
        $this->assertEqualsWithDelta(810, $portfolio->holdingInvested($holding, 'EUR'), 0.01);
    }

    public function test_real_estate_invested_equity_nets_off_the_mortgage_down_payment(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR']);

        $flat = Asset::create(['type' => 'realestate', 'symbol' => 'FLAT', 'name' => 'Flat', 'currency' => 'EUR']);
        $holding = Holding::create([
            'account_id' => $account->id,
            'asset_id' => $flat->id,
            'quantity' => 1,
            'average_cost' => 100,              // purchase price
            'manual_value' => 150,               // current value
            'debt' => 40,                        // outstanding mortgage balance
            'mortgage_down_payment' => 20,        // cash paid upfront
        ]);

        $cash = Asset::create(['type' => 'cash', 'symbol' => 'CASH-EUR', 'name' => 'EUR cash', 'currency' => 'EUR']);
        Holding::create(['account_id' => $account->id, 'asset_id' => $cash->id, 'quantity' => 1, 'manual_value' => 500]);

        $portfolio = app(PortfolioService::class);

        // Invested equity is just the down payment (the rest was financed), not the full purchase price.
        $this->assertEqualsWithDelta(20, $portfolio->holdingEquityInvested($holding, 'EUR'), 0.01);
        // Net value 150 - 40 = 110; profit on the €20 actually put in is €90 = 450%.
        $this->assertEqualsWithDelta(450, $portfolio->holdingEquityGainPct($holding, 'EUR'), 0.01);

        $overview = $portfolio->overview($user);

        // Total invested equity excludes the cash holding entirely.
        $this->assertEqualsWithDelta(20, $overview['total_equity_invested'], 0.01);
        $this->assertEqualsWithDelta(90, $overview['total_equity_gain'], 0.01);
        $this->assertEqualsWithDelta(450, $overview['total_equity_gain_pct'], 0.01);
    }

    public function test_real_estate_bought_outright_uses_full_price_as_equity_when_no_down_payment_set(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR']);

        $flat = Asset::create(['type' => 'realestate', 'symbol' => 'FLAT2', 'name' => 'Flat 2', 'currency' => 'EUR']);
        $holding = Holding::create([
            'account_id' => $account->id,
            'asset_id' => $flat->id,
            'quantity' => 1,
            'average_cost' => 100,
            'manual_value' => 120,
            // No debt, no mortgage_down_payment — bought outright with cash.
        ]);

        $portfolio = app(PortfolioService::class);

        $this->assertEqualsWithDelta(100, $portfolio->holdingEquityInvested($holding, 'EUR'), 0.01);
        $this->assertEqualsWithDelta(20, $portfolio->holdingEquityGainPct($holding, 'EUR'), 0.01);
    }

    public function test_real_estate_ownership_share_scales_every_figure(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR']);

        // A 100k flat, 50% owned: purchase/value/debt/down-payment are all
        // whole-property figures, halved by the ownership share.
        $flat = Asset::create(['type' => 'realestate', 'symbol' => 'FLAT3', 'name' => 'Flat 3', 'currency' => 'EUR']);
        $holding = Holding::create([
            'account_id' => $account->id,
            'asset_id' => $flat->id,
            'quantity' => 1,
            'average_cost' => 100000,
            'manual_value' => 130000,
            'debt' => 80000,
            'mortgage_down_payment' => 10000,
            'ownership_pct' => 50,
        ]);

        $this->assertEqualsWithDelta(65000, $holding->grossValue(), 0.01);   // 130,000 * 50%
        $this->assertEqualsWithDelta(40000, $holding->debtAmount(), 0.01);   // 80,000 * 50%
        $this->assertEqualsWithDelta(25000, $holding->netValue(), 0.01);     // 65,000 - 40,000
        $this->assertEqualsWithDelta(5000, $holding->investedEquity(), 0.01); // 10,000 * 50%
        $this->assertEqualsWithDelta(20000, $holding->equityGain(), 0.01);   // 25,000 - 5,000
        $this->assertEqualsWithDelta(400, $holding->equityGainPct(), 0.01);  // same % as full ownership

        // Default (no ownership_pct passed) is 100% — existing rows are unaffected.
        $full = Holding::create([
            'account_id' => $account->id,
            'asset_id' => Asset::create(['type' => 'realestate', 'symbol' => 'FLAT4', 'name' => 'Flat 4', 'currency' => 'EUR'])->id,
            'quantity' => 1,
            'average_cost' => 100000,
            'manual_value' => 130000,
            'debt' => 80000,
            'mortgage_down_payment' => 10000,
        ]);
        $this->assertEqualsWithDelta(130000, $full->grossValue(), 0.01);
        $this->assertEqualsWithDelta(10000, $full->investedEquity(), 0.01);
    }

    public function test_user_can_add_a_position(): void
    {
        $user = $this->makePortfolio();
        $account = $user->accounts()->first();

        $this->actingAs($user)->post('/positions', [
            'account_id' => $account->id,
            'type' => 'crypto',
            'symbol' => 'SOL',
            'name' => 'Solana',
            'currency' => 'USD',
            'quantity' => 10,
            'average_cost' => 100,
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', ['symbol' => 'SOL', 'type' => 'crypto']);
        $this->assertDatabaseHas('holdings', ['quantity' => 10]);
    }
}
