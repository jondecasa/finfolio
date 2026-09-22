<?php

namespace App\Http\Controllers;

use App\Services\Notifications\TelegramNotifier;
use App\Services\Notifications\WebPushNotifier;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function connectTelegram(Request $request, TelegramNotifier $telegram)
    {
        return back()->with('telegram_link_url', $telegram->linkUrl($request->user()));
    }

    public function checkTelegram(Request $request, TelegramNotifier $telegram)
    {
        $linked = $telegram->tryCompleteLink($request->user());

        return back()->with('status', $linked
            ? 'Telegram connected.'
            : "Not linked yet — open the link and press Start in Telegram first, then try again.");
    }

    public function disconnectTelegram(Request $request, TelegramNotifier $telegram)
    {
        $telegram->unlink($request->user());

        return back()->with('status', 'Telegram disconnected.');
    }

    public function subscribePush(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
            ],
        );

        return response()->json(['status' => 'subscribed']);
    }

    public function unsubscribePush(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'string']]);

        $request->user()->pushSubscriptions()
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json(['status' => 'unsubscribed']);
    }

    public function testPush(Request $request, WebPushNotifier $webPush)
    {
        $webPush->send($request->user(), 'Finfolio', 'Push notifications are working.', url('/alerts'));

        return back()->with('status', 'Test notification sent.');
    }
}
