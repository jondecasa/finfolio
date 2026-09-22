<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/** Sends browser Web Push notifications to every device a user subscribed on. */
class WebPushNotifier
{
    public function __construct(
        protected ?string $publicKey,
        protected ?string $privateKey,
        protected ?string $subject,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->publicKey) && filled($this->privateKey);
    }

    public function send(User $user, string $title, string $body, ?string $url = null): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $subscriptions = $user->pushSubscriptions()->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $this->subject ?: 'mailto:noreply@finfolio.app',
                'publicKey' => $this->publicKey,
                'privateKey' => $this->privateKey,
            ],
        ]);

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url ?: '/']);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->p256dh,
                    'authToken' => $subscription->auth,
                    // Modern browsers negotiate rfc8291 (aes128gcm); the
                    // library's own default is the outdated pre-standard
                    // "aesgcm" encoding, which real subscriptions no longer
                    // understand.
                    'contentEncoding' => 'aes128gcm',
                ]),
                $payload,
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                // Browser data was cleared or the user unsubscribed elsewhere.
                $subscriptions->firstWhere('endpoint', $report->getEndpoint())?->delete();
            } elseif (! $report->isSuccess()) {
                Log::warning('Web push failed: '.$report->getReason());
            }
        }
    }
}
