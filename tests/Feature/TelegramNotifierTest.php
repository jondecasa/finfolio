<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Notifications\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotifierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'finfolio.telegram.bot_token' => 'test-token',
            'finfolio.telegram.bot_username' => 'FinfolioTestBot',
        ]);
    }

    private function notifier(): TelegramNotifier
    {
        return new TelegramNotifier(
            config('finfolio.telegram.bot_token'),
            config('finfolio.telegram.bot_username'),
        );
    }

    public function test_link_url_generates_and_persists_a_token(): void
    {
        $user = User::factory()->create();
        $this->assertNull($user->telegram_link_token);

        $url = $this->notifier()->linkUrl($user);

        $user->refresh();
        $this->assertNotNull($user->telegram_link_token);
        $this->assertStringContainsString('https://t.me/FinfolioTestBot?start=', $url);
        $this->assertStringContainsString($user->telegram_link_token, $url);

        // Calling it again reuses the same token rather than generating a new one.
        $this->assertSame($url, $this->notifier()->linkUrl($user->fresh()));
    }

    public function test_try_complete_link_saves_the_chat_id_from_a_matching_start_message(): void
    {
        $user = User::factory()->create(['telegram_link_token' => 'abc123']);

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    ['message' => ['text' => '/start abc123', 'chat' => ['id' => 999888777]]],
                ],
            ]),
        ]);

        $linked = $this->notifier()->tryCompleteLink($user);

        $this->assertTrue($linked);
        $user->refresh();
        $this->assertSame('999888777', $user->telegram_chat_id);
        $this->assertNull($user->telegram_link_token);
    }

    public function test_try_complete_link_ignores_a_start_message_for_a_different_token(): void
    {
        $user = User::factory()->create(['telegram_link_token' => 'abc123']);

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    ['message' => ['text' => '/start someone-elses-token', 'chat' => ['id' => 111]]],
                ],
            ]),
        ]);

        $this->assertFalse($this->notifier()->tryCompleteLink($user));
        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_send_posts_to_the_bot_api_with_the_users_chat_id(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '555']);

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->assertTrue($this->notifier()->send($user, 'NVDA is above $150'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && $request['chat_id'] === '555'
            && $request['text'] === 'NVDA is above $150');
    }

    public function test_send_is_a_noop_without_a_linked_chat_id(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => null]);

        Http::fake();

        $this->assertFalse($this->notifier()->send($user, 'hello'));
        Http::assertNothingSent();
    }
}
