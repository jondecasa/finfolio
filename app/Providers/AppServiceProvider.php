<?php

namespace App\Providers;

use App\Services\Notifications\TelegramNotifier;
use App\Services\Notifications\WebPushNotifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TelegramNotifier::class, fn () => new TelegramNotifier(
            config('finfolio.telegram.bot_token'),
            config('finfolio.telegram.bot_username'),
        ));

        $this->app->singleton(WebPushNotifier::class, fn () => new WebPushNotifier(
            config('finfolio.webpush.public_key'),
            config('finfolio.webpush.private_key'),
            config('finfolio.webpush.subject'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
