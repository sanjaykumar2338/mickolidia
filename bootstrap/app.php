<?php

use App\Http\Middleware\AdminBasicAuth;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\EnsureTrialSession;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('trading:sync-accounts --queued')
            ->cron((string) config('trading.sync.cron', '*/15 * * * *'))
            ->when(fn (): bool => (bool) config('trading.sync.enabled', false));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'payments/stripe/webhook',
        ]);

        $middleware->alias([
            'admin.basic' => AdminBasicAuth::class,
            'trial.session' => EnsureTrialSession::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
