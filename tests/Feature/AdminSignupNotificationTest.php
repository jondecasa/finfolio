<?php

namespace Tests\Feature;

use App\Support\Honeypot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminSignupNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            Honeypot::STAMP => Crypt::encryptString((string) (now()->getTimestamp() - 10)),
            Honeypot::TRAP => '',
        ];
    }

    public function test_owner_gets_a_telegram_message_when_someone_registers(): void
    {
        config([
            'finfolio.admin_notify.telegram_bot_token' => 'admin-token',
            'finfolio.admin_notify.telegram_chat_id' => '4242',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->post('/register', $this->payload())->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'botadmin-token/sendMessage')
            && $request['chat_id'] === '4242'
            && str_contains($request['text'], 'new@example.com')
            && str_contains($request['text'], 'New Person'));
    }

    public function test_registration_works_and_sends_nothing_when_not_configured(): void
    {
        config([
            'finfolio.admin_notify.telegram_bot_token' => null,
            'finfolio.admin_notify.telegram_chat_id' => null,
        ]);
        Http::fake();

        $this->post('/register', $this->payload())->assertRedirect();

        $this->assertAuthenticated();
        Http::assertNothingSent();
    }

    public function test_a_failing_telegram_never_breaks_registration(): void
    {
        config([
            'finfolio.admin_notify.telegram_bot_token' => 'admin-token',
            'finfolio.admin_notify.telegram_chat_id' => '4242',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);

        $this->post('/register', $this->payload())->assertRedirect();

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }
}
