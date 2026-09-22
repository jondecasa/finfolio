<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sends messages via a Telegram bot. A bot can't message a user first, so
 * linking works as a one-time handshake: the user opens a deep link that
 * starts a chat with the bot carrying a link token, and we poll Telegram's
 * getUpdates for that token to learn the resulting chat_id.
 */
class TelegramNotifier
{
    public function __construct(
        protected ?string $botToken,
        protected ?string $botUsername,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->botToken) && filled($this->botUsername);
    }

    /** The link the user opens in Telegram to start a chat with the bot. */
    public function linkUrl(User $user): string
    {
        if (! $user->telegram_link_token) {
            $user->forceFill(['telegram_link_token' => Str::random(24)])->save();
        }

        return "https://t.me/{$this->botUsername}?start={$user->telegram_link_token}";
    }

    /**
     * Look for a "/start {token}" message matching this user's pending link
     * token and, if found, save the chat_id it came from. Returns whether
     * linking just completed.
     */
    public function tryCompleteLink(User $user): bool
    {
        if (! $this->isConfigured() || ! $user->telegram_link_token) {
            return false;
        }

        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$this->botToken}/getUpdates");
        } catch (\Throwable $e) {
            Log::warning('Telegram getUpdates failed: '.$e->getMessage());

            return false;
        }

        if (! $response->ok()) {
            return false;
        }

        foreach ($response->json('result', []) as $update) {
            $text = $update['message']['text'] ?? '';
            $chatId = $update['message']['chat']['id'] ?? null;

            if ($chatId && Str::startsWith($text, '/start '.$user->telegram_link_token)) {
                $user->forceFill([
                    'telegram_chat_id' => (string) $chatId,
                    'telegram_link_token' => null,
                ])->save();

                return true;
            }
        }

        return false;
    }

    public function unlink(User $user): void
    {
        $user->forceFill(['telegram_chat_id' => null, 'telegram_link_token' => null])->save();
    }

    public function send(User $user, string $text): bool
    {
        if (! $this->isConfigured() || ! $user->telegram_chat_id) {
            return false;
        }

        try {
            return Http::timeout(10)->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $user->telegram_chat_id,
                'text' => $text,
            ])->ok();
        } catch (\Throwable $e) {
            Log::warning('Telegram sendMessage failed: '.$e->getMessage());

            return false;
        }
    }
}
