<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\PriceAlert;
use App\Models\User;
use App\Services\AlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin FX + swallow any outbound call (Telegram etc. are unconfigured
        // in tests anyway) so nothing here depends on the network.
        Http::fake([
            'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['USD' => 1.0, 'EUR' => 0.9]]),
            '*' => Http::response([], 200),
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/alerts')->assertRedirect('/login');
    }

    public function test_alerts_page_prompts_to_connect_telegram_when_configured_but_not_linked(): void
    {
        config(['finfolio.telegram.bot_token' => 'test-token', 'finfolio.telegram.bot_username' => 'FinfolioTestBot']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/alerts')
            ->assertOk()
            ->assertSee('Connect Telegram to get notified')
            ->assertSee('Open Telegram');
    }

    public function test_alerts_page_has_no_telegram_prompt_once_linked(): void
    {
        config(['finfolio.telegram.bot_token' => 'test-token', 'finfolio.telegram.bot_username' => 'FinfolioTestBot']);
        $user = User::factory()->create(['telegram_chat_id' => '12345']);

        $this->actingAs($user)->get('/alerts')
            ->assertOk()
            ->assertDontSee('Connect Telegram to get notified');
    }

    public function test_user_can_create_a_price_alert_for_an_asset_they_dont_hold(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);

        $this->actingAs($user)->post('/alerts', [
            'type' => 'stock',
            'symbol' => 'NVDA',
            'name' => 'NVIDIA Corp',
            'currency' => 'USD',
            'condition' => 'above',
            'target_price' => 150,
        ])->assertRedirect(route('alerts.index'));

        $this->assertDatabaseHas('price_alerts', [
            'user_id' => $user->id,
            'condition' => 'above',
            'target_price' => 150,
            'active' => true,
        ]);
        $this->assertDatabaseHas('assets', ['type' => 'stock', 'symbol' => 'NVDA']);
    }

    public function test_alert_triggers_and_deactivates_when_condition_is_met(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $asset = Asset::create([
            'type' => 'stock', 'symbol' => 'NVDA', 'name' => 'NVIDIA Corp', 'currency' => 'USD',
            'current_price' => 155, 'previous_close' => 150, 'price_updated_at' => now(),
        ]);
        $alert = PriceAlert::create([
            'user_id' => $user->id, 'asset_id' => $asset->id,
            'condition' => 'above', 'target_price' => 150,
        ]);

        app(AlertService::class)->checkAsset($asset);

        $alert->refresh();
        $this->assertFalse($alert->active);
        $this->assertNotNull($alert->triggered_at);
    }

    public function test_alert_does_not_trigger_when_condition_is_not_met(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $asset = Asset::create([
            'type' => 'stock', 'symbol' => 'NVDA', 'name' => 'NVIDIA Corp', 'currency' => 'USD',
            'current_price' => 140, 'previous_close' => 150, 'price_updated_at' => now(),
        ]);
        $alert = PriceAlert::create([
            'user_id' => $user->id, 'asset_id' => $asset->id,
            'condition' => 'above', 'target_price' => 150,
        ]);

        app(AlertService::class)->checkAsset($asset);

        $this->assertTrue($alert->fresh()->active);
        $this->assertNull($alert->fresh()->triggered_at);
    }

    public function test_a_below_condition_triggers_when_price_drops_to_or_under_target(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $asset = Asset::create([
            'type' => 'crypto', 'symbol' => 'BTC', 'name' => 'Bitcoin', 'currency' => 'USD',
            'current_price' => 50000, 'previous_close' => 60000, 'price_updated_at' => now(),
        ]);
        $alert = PriceAlert::create([
            'user_id' => $user->id, 'asset_id' => $asset->id,
            'condition' => 'below', 'target_price' => 55000,
        ]);

        app(AlertService::class)->checkAsset($asset);

        $this->assertFalse($alert->fresh()->active);
    }

    public function test_a_triggered_alert_does_not_refire_on_a_later_refresh(): void
    {
        $user = User::factory()->create(['base_currency' => 'EUR']);
        $asset = Asset::create([
            'type' => 'stock', 'symbol' => 'NVDA', 'name' => 'NVIDIA Corp', 'currency' => 'USD',
            'current_price' => 155, 'previous_close' => 150, 'price_updated_at' => now(),
        ]);
        $alert = PriceAlert::create([
            'user_id' => $user->id, 'asset_id' => $asset->id,
            'condition' => 'above', 'target_price' => 150,
        ]);

        $service = app(AlertService::class);
        $service->checkAsset($asset);
        $firstTrigger = $alert->fresh()->triggered_at;

        // Price stays above target on the next refresh — already-triggered
        // alert must stay inactive, not renotify every hour.
        $asset->update(['current_price' => 160]);
        $service->checkAsset($asset);

        $this->assertTrue($firstTrigger->equalTo($alert->fresh()->triggered_at));
    }

    public function test_user_cannot_view_edit_rearm_or_delete_another_users_alert(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $asset = Asset::create(['type' => 'stock', 'symbol' => 'NVDA', 'name' => 'NVIDIA', 'currency' => 'USD']);
        $alert = PriceAlert::create([
            'user_id' => $owner->id, 'asset_id' => $asset->id,
            'condition' => 'above', 'target_price' => 150, 'active' => false, 'triggered_at' => now(),
        ]);

        $this->actingAs($stranger)->post(route('alerts.rearm', $alert))->assertForbidden();
        $this->actingAs($stranger)->delete(route('alerts.destroy', $alert))->assertForbidden();
        $this->actingAs($stranger)->get(route('alerts.edit', $alert))->assertForbidden();
        $this->actingAs($stranger)->put(route('alerts.update', $alert), [
            'condition' => 'below', 'target_price' => 100,
        ])->assertForbidden();
    }

    public function test_user_can_update_an_alerts_condition_and_target_price(): void
    {
        $user = User::factory()->create();
        $asset = Asset::create(['type' => 'stock', 'symbol' => 'NVDA', 'name' => 'NVIDIA', 'currency' => 'USD']);
        $alert = PriceAlert::create([
            'user_id' => $user->id, 'asset_id' => $asset->id,
            'condition' => 'above', 'target_price' => 150,
        ]);

        $this->actingAs($user)->put(route('alerts.update', $alert), [
            'condition' => 'below', 'target_price' => 90,
        ])->assertRedirect(route('alerts.index'));

        $alert->refresh();
        $this->assertSame('below', $alert->condition);
        $this->assertEqualsWithDelta(90, $alert->target_price, 0.01);
    }

    public function test_updating_a_triggered_alert_re_arms_it(): void
    {
        $user = User::factory()->create();
        $asset = Asset::create(['type' => 'stock', 'symbol' => 'NVDA', 'name' => 'NVIDIA', 'currency' => 'USD']);
        $alert = PriceAlert::create([
            'user_id' => $user->id, 'asset_id' => $asset->id,
            'condition' => 'above', 'target_price' => 150, 'active' => false, 'triggered_at' => now(),
        ]);

        $this->actingAs($user)->put(route('alerts.update', $alert), [
            'condition' => 'above', 'target_price' => 200,
        ])->assertRedirect();

        $alert->refresh();
        $this->assertTrue($alert->active);
        $this->assertNull($alert->triggered_at);
    }

    public function test_rearming_reactivates_a_triggered_alert(): void
    {
        $user = User::factory()->create();
        $asset = Asset::create(['type' => 'stock', 'symbol' => 'NVDA', 'name' => 'NVIDIA', 'currency' => 'USD']);
        $alert = PriceAlert::create([
            'user_id' => $user->id, 'asset_id' => $asset->id,
            'condition' => 'above', 'target_price' => 150, 'active' => false, 'triggered_at' => now(),
        ]);

        $this->actingAs($user)->post(route('alerts.rearm', $alert))->assertRedirect();

        $alert->refresh();
        $this->assertTrue($alert->active);
        $this->assertNull($alert->triggered_at);
    }

    public function test_deleting_an_alert_removes_it(): void
    {
        $user = User::factory()->create();
        $asset = Asset::create(['type' => 'stock', 'symbol' => 'NVDA', 'name' => 'NVIDIA', 'currency' => 'USD']);
        $alert = PriceAlert::create([
            'user_id' => $user->id, 'asset_id' => $asset->id,
            'condition' => 'above', 'target_price' => 150,
        ]);

        $this->actingAs($user)->delete(route('alerts.destroy', $alert))->assertRedirect();

        $this->assertDatabaseMissing('price_alerts', ['id' => $alert->id]);
    }

    public function test_push_subscription_can_be_stored_and_removed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/notifications/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['p256dh' => 'pubkey', 'auth' => 'authsecret'],
        ])->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ]);

        $this->actingAs($user)->deleteJson('/notifications/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['user_id' => $user->id]);
    }
}
