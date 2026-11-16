<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Sends the app owner a Telegram message about something that happened in the app. */
class AdminNotifier
{
    public function isConfigured(): bool
    {
        return filled(config('finfolio.admin_notify.telegram_bot_token'))
            && filled(config('finfolio.admin_notify.telegram_chat_id'));
    }

    /** Never throws — an owner notification must not break whatever triggered it. */
    public function send(string $text): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $token = config('finfolio.admin_notify.telegram_bot_token');

        try {
            $response = Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => config('finfolio.admin_notify.telegram_chat_id'),
                'text' => $text,
            ]);

            if (! $response->ok()) {
                Log::warning('Admin Telegram notification rejected: '.$response->body());
            }

            return $response->ok();
        } catch (\Throwable $e) {
            Log::warning('Admin Telegram notification failed: '.$e->getMessage());

            return false;
        }
    }
}
