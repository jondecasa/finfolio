<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Holding;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['USD' => 1.0, 'EUR' => 0.9]]),
            '*' => Http::response([], 200),
        ]);
    }

    /** @return array{0: User, 1: Account} */
    private function userWithAccount(string $currency = 'EUR'): array
    {
        $user = User::factory()->create(['base_currency' => $currency]);
        $account = $user->accounts()->create(['name' => 'Main', 'currency' => $currency, 'is_default' => true]);

        return [$user, $account];
    }

    private function pricedHolding($account, float $qty, float $avg, float $price, string $ccy = 'USD'): Holding
    {
        $asset = Asset::create([
            'type' => 'crypto', 'symbol' => 'BTC'.uniqid(), 'name' => 'Bitcoin', 'currency' => $ccy,
            'current_price' => $price, 'previous_close' => $price, 'price_updated_at' => now(),
        ]);

        return Holding::create([
            'account_id' => $account->id, 'asset_id' => $asset->id,
            'quantity' => $qty, 'average_cost' => $avg, 'cost_currency' => $ccy,
        ]);
    }

    private function plan(Holding $holding, array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'holding_id' => $holding->id,
            'target' => 'quantity',
            'direction' => 'in',
            'amount_kind' => 'units',
            'amount' => 1,
            'currency' => null,
            'frequency' => 'monthly',
            'starts_on' => CarbonImmutable::today(),
            'next_run_on' => CarbonImmutable::today(),
            'active' => true,
        ], $overrides));
    }

    public function test_user_can_create_a_plan(): void
    {
        [$user, $account] = $this->userWithAccount();
        $holding = $this->pricedHolding($account, 1.0, 100, 100);

        $this->actingAs($user)->post('/plans', [
            'holding_id' => $holding->id,
            'target' => 'quantity',
            'direction' => 'in',
            'amount_kind' => 'units',
            'amount' => 0.25,
            'frequency' => 'monthly',
            'starts_on' => CarbonImmutable::today()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('plans', ['holding_id' => $holding->id, 'amount' => 0.25, 'target' => 'quantity']);
    }

    public function test_buy_units_plan_raises_quantity_and_reweights_average_cost(): void
    {
        [$user, $account] = $this->userWithAccount();
        $holding = $this->pricedHolding($account, 1.5, 20000, 30000, 'USD');
        $this->plan($holding, ['amount' => 1, 'direction' => 'in']);

        $this->artisan('plans:run', ['--date' => CarbonImmutable::today()->toDateString()])->assertExitCode(0);

        $holding->refresh();
        $this->assertEqualsWithDelta(2.5, $holding->quantity, 1e-6);
        $this->assertEqualsWithDelta(24000, $holding->average_cost, 1e-4);
        $this->assertDatabaseHas('plan_runs', ['status' => 'applied']);
    }

    public function test_buy_into_a_stock_reweights_average_over_all_shares(): void
    {
        // User's example: hold 1 share bought at 100, plan buys 1 more at 150 -> avg 125.
        [$user, $account] = $this->userWithAccount('EUR');
        $asset = Asset::create([
            'type' => 'stock', 'symbol' => 'ACME', 'name' => 'Acme', 'currency' => 'EUR',
            'current_price' => 150, 'previous_close' => 150, 'price_updated_at' => now(),
        ]);
        $holding = Holding::create([
            'account_id' => $account->id, 'asset_id' => $asset->id,
            'quantity' => 1, 'average_cost' => 100, 'cost_currency' => 'EUR',
        ]);
        $this->plan($holding, ['amount' => 1, 'direction' => 'in']);

        $this->artisan('plans:run', ['--date' => CarbonImmutable::today()->toDateString()]);

        $holding->refresh();
        $this->assertEqualsWithDelta(2, $holding->quantity, 1e-9);
        $this->assertEqualsWithDelta(125, $holding->average_cost, 1e-9);
    }

    public function test_buy_into_a_position_with_no_recorded_cost_basis(): void
    {
        // No average_cost on the holding: the untracked shares count as zero cost,
        // consistent with Holding::costBasis(). 1 untracked + 1 bought at 150 -> 75.
        [$user, $account] = $this->userWithAccount('EUR');
        $asset = Asset::create([
            'type' => 'stock', 'symbol' => 'NOBASIS', 'name' => 'No basis', 'currency' => 'EUR',
            'current_price' => 150, 'previous_close' => 150, 'price_updated_at' => now(),
        ]);
        $holding = Holding::create([
            'account_id' => $account->id, 'asset_id' => $asset->id, 'quantity' => 1,
        ]);
        $this->plan($holding, ['amount' => 1, 'direction' => 'in']);

        $this->artisan('plans:run', ['--date' => CarbonImmutable::today()->toDateString()]);

        $holding->refresh();
        $this->assertEqualsWithDelta(75, $holding->average_cost, 1e-9);
    }

    public function test_buy_cash_plan_derives_units_from_the_days_price(): void
    {
        [$user, $account] = $this->userWithAccount('EUR');
        // Asset trades in USD at 100; holding cost currency EUR. 1 USD = 0.9 EUR.
        $holding = $this->pricedHolding($account, 0.0, 0, 100, 'USD');
        $holding->update(['cost_currency' => 'EUR']);

        $this->plan($holding, ['amount_kind' => 'cash', 'amount' => 180, 'currency' => 'EUR']);

        $this->artisan('plans:run', ['--date' => CarbonImmutable::today()->toDateString()]);

        $holding->refresh();
        // 180 EUR / (100 USD * 0.9) = 2.0 units
        $this->assertEqualsWithDelta(2.0, $holding->quantity, 1e-6);
    }

    public function test_sell_that_would_go_below_zero_is_skipped(): void
    {
        [$user, $account] = $this->userWithAccount();
        $holding = $this->pricedHolding($account, 0.5, 100, 100);
        $this->plan($holding, ['direction' => 'out', 'amount' => 1]);

        $this->artisan('plans:run', ['--date' => CarbonImmutable::today()->toDateString()]);

        $holding->refresh();
        $this->assertEqualsWithDelta(0.5, $holding->quantity, 1e-9);
        $this->assertDatabaseHas('plan_runs', ['status' => 'skipped']);
    }

    public function test_sell_plan_lowers_quantity_and_keeps_average_cost(): void
    {
        [$user, $account] = $this->userWithAccount();
        $holding = $this->pricedHolding($account, 3.0, 100, 250);
        $this->plan($holding, ['direction' => 'out', 'amount' => 1]);

        $this->artisan('plans:run', ['--date' => CarbonImmutable::today()->toDateString()]);

        $holding->refresh();
        $this->assertEqualsWithDelta(2.0, $holding->quantity, 1e-9);
        $this->assertEqualsWithDelta(100, $holding->average_cost, 1e-9);
    }

    public function test_real_estate_debt_reduction_plan_lowers_debt(): void
    {
        [$user, $account] = $this->userWithAccount('EUR');
        $flat = Asset::create(['type' => 'realestate', 'symbol' => 'FLAT', 'name' => 'Flat', 'currency' => 'EUR']);
        $holding = Holding::create([
            'account_id' => $account->id, 'asset_id' => $flat->id,
            'quantity' => 1, 'average_cost' => 200000, 'manual_value' => 200000, 'debt' => 10000,
        ]);

        $plan = $this->plan($holding, [
            'target' => 'debt', 'direction' => 'out', 'amount_kind' => 'cash', 'amount' => 100, 'currency' => 'EUR',
        ]);

        $this->artisan('plans:run', ['--date' => CarbonImmutable::today()->toDateString()]);
        $holding->refresh();
        $this->assertEqualsWithDelta(9900, $holding->debt, 1e-6);

        // Overpaying clamps the debt at zero rather than going negative.
        $plan->refresh();
        $plan->update(['amount' => 999999, 'next_run_on' => CarbonImmutable::today()]);
        $this->artisan('plans:run', ['--date' => CarbonImmutable::today()->toDateString()]);
        $holding->refresh();
        $this->assertEqualsWithDelta(0, $holding->debt, 1e-6);
    }

    public function test_missed_periods_are_not_backfilled(): void
    {
        [$user, $account] = $this->userWithAccount();
        $holding = $this->pricedHolding($account, 1.0, 100, 100);
        $this->plan($holding, [
            'amount' => 1,
            'starts_on' => CarbonImmutable::today()->subMonths(3),
            'next_run_on' => CarbonImmutable::today()->subMonths(3),
        ]);

        $this->artisan('plans:run', ['--date' => CarbonImmutable::today()->toDateString()]);

        $holding->refresh();
        $this->assertEqualsWithDelta(2.0, $holding->quantity, 1e-6); // exactly one buy, not three
        $this->assertSame(1, $holding->plans()->first()->runs()->count());
        $this->assertTrue($holding->plans()->first()->next_run_on->isFuture());
    }

    public function test_run_ignores_paused_future_and_ended_plans(): void
    {
        [$user, $account] = $this->userWithAccount();
        $holding = $this->pricedHolding($account, 5.0, 100, 100);

        $this->plan($holding, ['amount' => 1, 'active' => false]);                                   // paused
        $this->plan($holding, ['amount' => 1, 'next_run_on' => CarbonImmutable::today()->addWeek()]); // future
        $this->plan($holding, ['amount' => 1, 'ends_on' => CarbonImmutable::today()->subDay()]);      // ended

        $this->artisan('plans:run', ['--date' => CarbonImmutable::today()->toDateString()]);

        $holding->refresh();
        $this->assertEqualsWithDelta(5.0, $holding->quantity, 1e-9);
        $this->assertDatabaseCount('plan_runs', 0);
    }

    public function test_run_now_endpoint_applies_one_movement_immediately(): void
    {
        [$user, $account] = $this->userWithAccount();
        $holding = $this->pricedHolding($account, 1.0, 100, 100);
        $plan = $this->plan($holding, ['amount' => 2, 'next_run_on' => CarbonImmutable::today()->addYear()]);

        $this->actingAs($user)->post("/plans/{$plan->id}/run")->assertRedirect();

        $holding->refresh();
        $this->assertEqualsWithDelta(3.0, $holding->quantity, 1e-6);
    }

    public function test_a_stranger_cannot_touch_another_users_plan(): void
    {
        [$user, $account] = $this->userWithAccount();
        $holding = $this->pricedHolding($account, 1.0, 100, 100);
        $plan = $this->plan($holding);

        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/plans/{$plan->id}")->assertForbidden();
        $this->actingAs($stranger)->get("/plans/{$plan->id}/edit")->assertForbidden();
        $this->actingAs($stranger)->post("/plans/{$plan->id}/run")->assertForbidden();
        $this->actingAs($stranger)->delete("/plans/{$plan->id}")->assertForbidden();
    }
}
