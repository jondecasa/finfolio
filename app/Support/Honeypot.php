<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Dependency-free honeypot for public forms.
 *
 *  - a decoy text field ({@see self::TRAP}) that must stay empty; real
 *    browsers never see it, automated form-fillers tend to complete it;
 *  - an encrypted "rendered at" timestamp ({@see self::STAMP}); a submit
 *    that arrives faster than a human could type is treated as a bot.
 */
class Honeypot
{
    /** Decoy field name — looks fillable, is hidden from humans. */
    public const TRAP = 'contact_url';

    /** Encrypted timestamp field name. */
    public const STAMP = 'form_rendered_at';

    /** Minimum seconds between rendering the form and submitting it. */
    public const MIN_SECONDS = 2;

    /** Fresh encrypted stamp for the current request. */
    public static function stamp(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    /**
     * Reject the request when the decoy is filled or it was submitted
     * implausibly fast / without a valid stamp.
     *
     * @throws ValidationException
     */
    public static function check(Request $request): void
    {
        $failed = filled($request->input(self::TRAP)) || ! self::submittedByHuman($request);

        if ($failed) {
            Log::channel('single')->info('Honeypot blocked a submission', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'ua' => (string) $request->userAgent(),
            ]);

            throw ValidationException::withMessages([
                'captcha' => __("We couldn't verify your submission. Please reload the page and try again."),
            ]);
        }
    }

    private static function submittedByHuman(Request $request): bool
    {
        $stamp = (string) $request->input(self::STAMP);

        if ($stamp === '') {
            return false;
        }

        try {
            $renderedAt = (int) Crypt::decryptString($stamp);
        } catch (DecryptException) {
            return false;
        }

        return $renderedAt > 0 && now()->getTimestamp() - $renderedAt >= self::MIN_SECONDS;
    }
}
