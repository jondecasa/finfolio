<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\PriceAlert;
use App\Services\Notifications\TelegramNotifier;
use App\Services\Notifications\WebPushNotifier;
use App\Support\Money;
use Illuminate\Support\Facades\Log;

/**
 * Checks an asset's just-refreshed live price against every active price
 * alert on it, notifying and deactivating any that fire. Called from
 * PriceService::refresh() so it runs after any refresh, scheduled or manual.
 */
class AlertService
{
    public function __construct(
        protected TelegramNotifier $telegram,
        protected WebPushNotifier $webPush,
    ) {}

    public function checkAsset(Asset $asset): void
    {
        if ($asset->current_price === null) {
            return;
        }

        $price = (float) $asset->current_price;

        PriceAlert::query()
            ->where('asset_id', $asset->id)
            ->where('active', true)
            ->with('user')
            ->get()
            ->each(function (PriceAlert $alert) use ($asset, $price) {
                if (! $alert->isMet($price)) {
                    return;
                }

                $alert->update(['active' => false, 'triggered_at' => now()]);
                $this->notify($alert, $asset, $price);
            });
    }

    protected function notify(PriceAlert $alert, Asset $asset, float $price): void
    {
        $currency = $asset->currency ?: 'USD';
        $text = "🔔 {$asset->name} ({$asset->symbol}) ".$alert->conditionLabel().' '
            .Money::format($alert->target_price, $currency)
            .'. Current price: '.Money::format($price, $currency);

        $telegramSent = $this->telegram->send($alert->user, $text);
        $this->webPush->send($alert->user, 'Price alert', $text, url('/alerts'));

        Log::info("Alert #{$alert->id} on {$asset->symbol} triggered for user #{$alert->user_id}.", [
            'telegram_configured' => $this->telegram->isConfigured(),
            'telegram_linked' => $alert->user->hasTelegramLinked(),
            'telegram_sent' => $telegramSent,
            'webpush_configured' => $this->webPush->isConfigured(),
            'webpush_subscriptions' => $alert->user->pushSubscriptions()->count(),
        ]);
    }
}
