<?php

namespace App\Providers;

use App\Services\EscortAds\AmaterkyEscortAdScraper;
use App\Services\EscortAds\EscortAdScraper;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmstoolsSmsGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EscortAdScraper::class, AmaterkyEscortAdScraper::class);
        $this->app->singleton(SmsGateway::class, SmstoolsSmsGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
