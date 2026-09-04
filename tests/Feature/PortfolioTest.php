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
            ->assertSee('Bitcoin')
            ->assertSee('Cash balance')
            ->assertSee('Liabilities');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/home')->assertRedirect('/login');
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
