<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Thin wrapper around Google reCAPTCHA v3 "siteverify".
 *
 * Disabled (always passes) until both keys are configured, so local dev
 * and the test suite need no network and no secrets.
 */
class Recaptcha
{
    public static function enabled(): bool
    {
        return filled(config('services.recaptcha.site_key'))
            && filled(config('services.recaptcha.secret'));
    }

    /**
     * Verify the token that the front-end obtained for $action.
     *
     * @throws ValidationException
     */
    public static function verify(Request $request, string $action): void
    {
        if (! self::enabled()) {
            return;
        }

        $token = (string) $request->input('g-recaptcha-response');
        $ok = false;

        if ($token !== '') {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => config('services.recaptcha.secret'),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);

            $body = $response->json() ?? [];

            $ok = ($body['success'] ?? false) === true
                && ($body['action'] ?? $action) === $action
                && ((float) ($body['score'] ?? 0)) >= (float) config('services.recaptcha.min_score');

            if (! $ok) {
                Log::channel('single')->warning('reCAPTCHA rejected a submission', [
                    'ip' => $request->ip(),
                    'action' => $action,
                    'result' => $body,
                ]);
            }
        }

        if (! $ok) {
            throw ValidationException::withMessages([
                'captcha' => __("We couldn't verify that you're human. Please reload the page and try again."),
            ]);
        }
    }
}
