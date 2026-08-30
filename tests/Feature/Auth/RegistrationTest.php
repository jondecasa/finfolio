<?php

namespace Tests\Feature\Auth;

use App\Support\Honeypot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** A valid registration payload, including passable anti-bot fields. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            Honeypot::STAMP => Crypt::encryptString((string) (now()->getTimestamp() - 10)),
            Honeypot::TRAP => '',
        ], $overrides);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', $this->payload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_registration_is_blocked_when_the_honeypot_is_filled(): void
    {
        $response = $this->from('/register')->post('/register', $this->payload([
            Honeypot::TRAP => 'https://spam.example',
        ]));

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('captcha');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_is_blocked_when_submitted_too_fast(): void
    {
        $response = $this->from('/register')->post('/register', $this->payload([
            Honeypot::STAMP => Honeypot::stamp(), // rendered "just now"
        ]));

        $response->assertSessionHasErrors('captcha');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_is_blocked_when_recaptcha_score_is_low(): void
    {
        config([
            'services.recaptcha.site_key' => 'test-site',
            'services.recaptcha.secret' => 'test-secret',
            'services.recaptcha.min_score' => 0.5,
        ]);

        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true, 'action' => 'register', 'score' => 0.1,
            ]),
        ]);

        $response = $this->from('/register')->post('/register', $this->payload([
            'g-recaptcha-response' => 'dummy-token',
        ]));

        $response->assertSessionHasErrors('captcha');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_passes_when_recaptcha_score_is_good(): void
    {
        config([
            'services.recaptcha.site_key' => 'test-site',
            'services.recaptcha.secret' => 'test-secret',
            'services.recaptcha.min_score' => 0.5,
        ]);

        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true, 'action' => 'register', 'score' => 0.9,
            ]),
        ]);

        $this->post('/register', $this->payload([
            'g-recaptcha-response' => 'dummy-token',
        ]));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }
}
