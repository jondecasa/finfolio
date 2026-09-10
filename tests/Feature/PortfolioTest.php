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

    public function test_analytics_net_value_is_net_of_debt_but_total_return_is_price_appreciation_only(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR']);
        $flat = Asset::create(['type' => 'realestate', 'symbol' => 'FLAT', 'name' => 'Flat', 'currency' => 'EUR']);
        // No mortgage_down_payment set — equity falls back to the full purchase price.
        Holding::create([
            'account_id' => $account->id, 'asset_id' => $flat->id,
            'quantity' => 1, 'average_cost' => 100, 'manual_value' => 150, 'debt' => 40,
        ]);

        // Net value 150 − 40 = 110 (debt reduces net worth). Total return is
        // price appreciation only, 150 − 100 = €50 — debt does NOT reduce it,
        // since without a tracked down payment there's nothing to net it against.
        $this->actingAs($user)->get('/analytics')
            ->assertOk()
            ->assertSee('€110.00') // Net value
            ->assertSee('€50.00'); // Total return
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

        // Plusvalía (price appreciation only) is 150 − 100 = €50 — mortgage
        // paydown isn't counted as profit. Equity invested is just the €10
        // down payment → total return is +€50.00 / +500%, not the +€10.00 /
        // +10% you'd get comparing net value to the full €100 price.
        $this->actingAs($user)->get('/analytics')
            ->assertOk()
            ->assertSee('€10.00')   // Equity invested tile
            ->assertSee('€50.00')   // Total return value
            ->assertSee('500.00%'); // Total return %
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

    public function test_positions_screen_shows_roe_only_for_real_estate(): void
    {
        $user = $this->makePortfolio(); // has a BTC holding, not real estate
        $account = $user->accounts()->first();

        $flat = Asset::create(['type' => 'realestate', 'symbol' => 'FLAT', 'name' => 'Flat', 'currency' => 'EUR']);
        Holding::create([
            'account_id' => $account->id, 'asset_id' => $flat->id,
            'quantity' => 1, 'average_cost' => 100, 'manual_value' => 150,
            'debt' => 40, 'mortgage_down_payment' => 10,
        ]);

        // Plusvalía = 150 − 100 = €50 (mortgage paydown isn't profit); equity
        // invested is the €10 down payment → ROE = 50/10 = 500%.
        $response = $this->actingAs($user)->get('/positions')->assertOk();
        $response->assertSee('ROE');
        $response->assertSee('500.00%');

        // Only the one real-estate holding gets a ROE line (BTC/ETH don't).
        $this->assertSame(1, substr_count($response->getContent(), 'ROE'));
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
        // Plusvalía (price appreciation, debt aside) is 150 − 100 = €50; profit
        // on the €20 actually put in is €50 = 250%. Paying down the mortgage
        // isn't counted as profit — see Holding::equityGain().
        $this->assertEqualsWithDelta(250, $portfolio->holdingEquityGainPct($holding, 'EUR'), 0.01);

        $overview = $portfolio->overview($user);

        // Total invested equity excludes the cash holding entirely.
        $this->assertEqualsWithDelta(20, $overview['total_equity_invested'], 0.01);
        $this->assertEqualsWithDelta(50, $overview['total_equity_gain'], 0.01);
        $this->assertEqualsWithDelta(250, $overview['total_equity_gain_pct'], 0.01);
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

    public function test_rented_real_estate_roce_counts_rent_but_roe_does_not(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR']);
        $flat = Asset::create(['type' => 'realestate', 'symbol' => 'FLAT', 'name' => 'Flat', 'currency' => 'EUR']);
        $holding = Holding::create([
            'account_id' => $account->id, 'asset_id' => $flat->id,
            'quantity' => 1, 'average_cost' => 1000, 'manual_value' => 1200,
            'mortgage_down_payment' => 200, 'monthly_rent' => 50,
        ]);

        $portfolio = app(PortfolioService::class);

        // ROCE: plusvalía (200) + a year of rent (50*12=600) = 800, over the
        // full purchase price (1000) = 80%. Unlevered — financing aside.
        $this->assertEqualsWithDelta(600, $holding->annualRentalIncome(), 0.01);
        $this->assertEqualsWithDelta(80, $portfolio->holdingRocePct($holding, 'EUR'), 0.01);
        $this->assertTrue($holding->isRented());

        // ROE is unaffected by rent — still just plusvalía (200) over the
        // €200 down payment = 100%, same as before rent was ever added.
        $this->assertEqualsWithDelta(100, $portfolio->holdingEquityGainPct($holding, 'EUR'), 0.01);

        // Not rented → no ROCE line, ROCE still computable but isRented() is false.
        $holding->monthly_rent = null;
        $this->assertFalse($holding->isRented());
        $this->assertEqualsWithDelta(0, $holding->annualRentalIncome(), 0.01);
    }

    public function test_positions_screen_shows_roce_only_when_rented(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR']);
        $flat = Asset::create(['type' => 'realestate', 'symbol' => 'FLAT', 'name' => 'Flat', 'currency' => 'EUR']);
        Holding::create([
            'account_id' => $account->id, 'asset_id' => $flat->id,
            'quantity' => 1, 'average_cost' => 1000, 'manual_value' => 1200,
            'mortgage_down_payment' => 200, 'monthly_rent' => 50,
        ]);

        $this->actingAs($user)->get('/positions')
            ->assertOk()
            ->assertSee('ROE')
            ->assertSee('ROCE')
            ->assertSee('80.00%'); // ROCE
    }

    public function test_allocation_has_an_invested_by_type_breakdown_excluding_cash(): void
    {
        $user = $this->makePortfolio(); // BTC 1@60000 (cost 40000), ETH 5@3000 (cost 2000/u)
        $account = $user->accounts()->first();
        $cash = Asset::create(['type' => 'cash', 'symbol' => 'CASH-EUR', 'name' => 'EUR cash', 'currency' => 'EUR']);
        Holding::create(['account_id' => $account->id, 'asset_id' => $cash->id, 'quantity' => 1, 'manual_value' => 5000]);

        $allocation = app(PortfolioService::class)->allocation($user);

        // Invested total excludes cash (cost basis 0) — only the crypto cost bases count.
        // BTC: 1*40000=40000 USD; ETH: 5*2000=10000 USD; *0.9 FX => 45000 EUR.
        $this->assertEqualsWithDelta(45000, $allocation['invested_total'], 0.01);

        $crypto = collect($allocation['by_type'])->firstWhere('key', 'crypto');
        $this->assertEqualsWithDelta(45000, $crypto['invested'], 0.01);
        $this->assertEqualsWithDelta(100, $crypto['invested_weight'], 0.01);

        $cashRow = collect($allocation['positions'])->firstWhere('symbol', 'CASH-EUR');
        $this->assertEqualsWithDelta(0, $cashRow['invested'], 0.01);
    }

    public function test_analytics_has_a_ring_tab_per_asset_type_present(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR']);

        // Three ETFs at different weights, plus one stock.
        foreach ([['A', 100, 4], ['B', 50, 3], ['C', 20, 2]] as [$sym, $price, $qty]) {
            $a = Asset::create(['type' => 'etf', 'symbol' => "ETF$sym", 'name' => "Fund $sym", 'currency' => 'EUR', 'current_price' => $price, 'price_updated_at' => now()]);
            Holding::create(['account_id' => $account->id, 'asset_id' => $a->id, 'quantity' => $qty, 'average_cost' => $price]);
        }
        $stock = Asset::create(['type' => 'stock', 'symbol' => 'ACME', 'name' => 'Acme Corp', 'currency' => 'EUR', 'current_price' => 10, 'price_updated_at' => now()]);
        Holding::create(['account_id' => $account->id, 'asset_id' => $stock->id, 'quantity' => 1, 'average_cost' => 10]);

        // A tab chip for each type present, and the per-type ring/list data.
        $res = $this->actingAs($user)->get('/analytics')->assertOk()
            ->assertSee('All positions')
            ->assertSee('>Type<', false)
            ->assertSee("tab = 'etf'", false)
            ->assertSee("tab = 'stock'", false)
            ->assertSee('Fund A')->assertSee('Fund B')->assertSee('Fund C');

        // ?tab=etf survives the controller's validation (not reset to "positions").
        $this->actingAs($user)->get('/analytics?tab=etf')->assertOk()->assertSee("tab: 'etf'", false);
        // An unknown tab falls back to "positions".
        $this->actingAs($user)->get('/analytics?tab=bogus')->assertOk()->assertSee("tab: 'positions'", false);

        // ETF ring weights are relative to the ETF subtotal (4*100 + 3*50 + 2*20
        // = 590), not the whole portfolio — so slice %s add up to 100 within ETF.
        $this->assertStringContainsString('67.8', $res->getContent()); // 400/590
    }

    public function test_analytics_chart_shows_asset_name_not_symbol(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR']);
        // A fund whose provider only ever gave us an ISIN — the name is stuck
        // as the ISIN too, but the chart should still key off whatever `name`
        // holds today, not the raw symbol (this used to be the same bug that
        // made a renamed real-estate position keep showing its old symbol).
        $fund = Asset::create([
            'type' => 'index', 'symbol' => 'IE00B4L5Y983', 'name' => 'iShares Core MSCI World', 'currency' => 'EUR',
            'current_price' => 100, 'price_updated_at' => now(),
        ]);
        Holding::create(['account_id' => $account->id, 'asset_id' => $fund->id, 'quantity' => 10, 'average_cost' => 90]);

        $response = $this->actingAs($user)->get('/analytics')->assertOk();
        $response->assertSee('iShares Core MSCI World');
        // The symbol shouldn't appear as a chart *label* — only as embedded
        // asset data elsewhere (logo-bubble fallback initials use substr(3)).
        $this->assertStringNotContainsString('"label":"IE00B4L5Y983"', $response->getContent());
    }

    public function test_user_can_rename_a_priced_asset_stuck_showing_its_isin(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR']);
        $fund = Asset::create([
            'type' => 'index', 'symbol' => 'IE00B4L5Y983', 'name' => 'IE00B4L5Y983', 'currency' => 'EUR',
            'current_price' => 100, 'price_updated_at' => now(),
        ]);
        $holding = Holding::create(['account_id' => $account->id, 'asset_id' => $fund->id, 'quantity' => 10, 'average_cost' => 90]);

        $this->actingAs($user)->put(route('holdings.update', $holding), [
            'account_id' => $account->id,
            'name' => 'iShares Core MSCI World',
            'quantity' => 10,
            'average_cost' => 90,
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', ['id' => $fund->id, 'name' => 'iShares Core MSCI World']);
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
        // equityGain is plusvalía only (gross - cost basis), not net of debt:
        // 65,000 - 50,000 = 15,000 (mortgage paydown isn't counted as profit).
        $this->assertEqualsWithDelta(15000, $holding->equityGain(), 0.01);
        $this->assertEqualsWithDelta(300, $holding->equityGainPct(), 0.01);  // 15,000 / 5,000

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

    public function test_user_can_rename_a_real_estate_position(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => 'EUR']);
        $flat = Asset::create(['type' => 'realestate', 'symbol' => 'FLAT', 'name' => 'Old name', 'currency' => 'EUR']);
        $holding = Holding::create([
            'account_id' => $account->id, 'asset_id' => $flat->id,
            'quantity' => 1, 'average_cost' => 100, 'manual_value' => 120,
        ]);

        $this->actingAs($user)->get(route('holdings.edit', $holding))
            ->assertOk()->assertSee('Old name');

        $this->actingAs($user)->put(route('holdings.update', $holding), [
            'account_id' => $account->id,
            'name' => 'New name',
            'quantity' => 1,
            'average_cost' => 100,
            'manual_value' => 120,
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', ['id' => $flat->id, 'name' => 'New name']);
    }

    public function test_two_identically_named_real_estate_positions_get_separate_assets(): void
    {
        $user = $this->makePortfolio();
        $account = $user->accounts()->first();
        $other = $user->accounts()->create(['name' => 'Side pot', 'currency' => 'EUR']);

        $payload = fn (int $accountId) => [
            'account_id' => $accountId,
            'type' => 'realestate',
            'symbol' => 'FLAT-IN-MADRID',
            'name' => 'Flat in Madrid',
            'currency' => 'EUR',
            'quantity' => 1,
            'average_cost' => 100000,
            'manual_price' => 120000,
        ];

        $this->actingAs($user)->post('/positions', $payload($account->id))->assertRedirect();
        $this->actingAs($user)->post('/positions', $payload($other->id))->assertRedirect();

        $assets = Asset::where('type', 'realestate')->where('name', 'Flat in Madrid')->get();
        $this->assertCount(2, $assets);
        $this->assertNotSame($assets[0]->id, $assets[1]->id);
        $this->assertNotSame($assets[0]->symbol, $assets[1]->symbol); // one gets a "-2" suffix

        // Renaming one doesn't touch the other.
        $holding = Holding::where('asset_id', $assets[0]->id)->firstOrFail();
        $this->actingAs($user)->put(route('holdings.update', $holding), [
            'account_id' => $holding->account_id,
            'name' => 'Renamed flat',
            'quantity' => 1,
            'average_cost' => 100000,
            'manual_value' => 120000,
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', ['id' => $assets[0]->id, 'name' => 'Renamed flat']);
        $this->assertDatabaseHas('assets', ['id' => $assets[1]->id, 'name' => 'Flat in Madrid']);
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

    public function test_notes_are_not_truncated_past_the_old_255_char_limit(): void
    {
        $user = $this->makePortfolio();
        $account = $user->accounts()->first();
        $btc = $account->holdings()->first();

        $longNote = str_repeat('a', 1000);

        $this->actingAs($user)->put(route('holdings.update', $btc), [
            'account_id' => $account->id,
            'name' => $btc->asset->name,
            'quantity' => $btc->quantity,
            'average_cost' => $btc->average_cost,
            'notes' => $longNote,
        ])->assertRedirect();

        $this->assertDatabaseHas('holdings', ['id' => $btc->id, 'notes' => $longNote]);
    }

    public function test_updating_a_position_redirects_back_to_where_it_was_opened_from(): void
    {
        $user = $this->makePortfolio();
        $account = $user->accounts()->first();
        $btc = $account->holdings()->first();

        // Simulate arriving at the edit page from Positions.
        $this->actingAs($user)->get('/positions');
        $this->actingAs($user)->get(route('holdings.edit', $btc));

        $response = $this->actingAs($user)->put(route('holdings.update', $btc), [
            'account_id' => $account->id,
            'name' => $btc->asset->name,
            'quantity' => $btc->quantity,
            'average_cost' => $btc->average_cost,
            'redirect_to' => url('/positions'),
        ]);

        $response->assertRedirect('/positions');
    }

    public function test_update_ignores_a_redirect_to_an_external_host(): void
    {
        $user = $this->makePortfolio();
        $account = $user->accounts()->first();
        $btc = $account->holdings()->first();

        $response = $this->actingAs($user)->put(route('holdings.update', $btc), [
            'account_id' => $account->id,
            'name' => $btc->asset->name,
            'quantity' => $btc->quantity,
            'average_cost' => $btc->average_cost,
            'redirect_to' => 'https://evil.example.com/phish',
        ]);

        $response->assertRedirect(route('analytics'));
    }
}
