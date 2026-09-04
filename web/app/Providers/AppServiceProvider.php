<?php

namespace App\Providers;

use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\Alerts\AlertDispatcher;
use App\Services\Alerts\LotMatcher;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AlertDispatcher::class, function ($app) {
            return new AlertDispatcher(
                $app->make(LotMatcher::class),
                [
                    $app->make(EmailChannel::class),
                    $app->make(WhatsAppChannel::class),
                ],
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
