<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Notifications\AdminNotifier;
use Illuminate\Auth\Events\Registered;

class NotifyAdminOfNewUser
{
    public function __construct(protected AdminNotifier $admin) {}

    public function handle(Registered $event): void
    {
        if (! $this->admin->isConfigured()) {
            return;
        }

        $user = $event->user;
        $text = "🆕 New Finfolio user: {$user->name} ({$user->email})\nTotal users: ".User::count();

        // After the response is sent, so a slow Telegram never delays sign-up.
        dispatch(fn () => $this->admin->send($text))->afterResponse();
    }
}
