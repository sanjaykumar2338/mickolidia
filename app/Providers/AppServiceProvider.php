<?php

namespace App\Providers;

use App\Services\Calendar\DemoEconomicCalendarService;
use App\Services\Calendar\EconomicCalendarServiceInterface;
use App\Services\Calendar\TradingEconomicsCalendarService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EconomicCalendarServiceInterface::class, function (Application $app): EconomicCalendarServiceInterface {
            $provider = strtolower((string) config('wolforix.economic_calendar.provider', 'tradingeconomics'));
            $tradingEconomicsKey = (string) config('services.trading_economics.api_key', '');

            return match ($provider) {
                'tradingeconomics', 'trading_economics' => filled($tradingEconomicsKey)
                    ? $app->make(TradingEconomicsCalendarService::class)
                    : $app->make(DemoEconomicCalendarService::class),
                'demo', 'stub', 'fmp', 'financialmodelingprep', 'econoday' => $app->make(DemoEconomicCalendarService::class),
                default => $app->make(DemoEconomicCalendarService::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
