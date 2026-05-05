<?php

use App\Http\Controllers\Api\TradingAccountMetricsController;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/mt5/accounts/{accountIdentifier}/metrics', TradingAccountMetricsController::class)
    ->name('api.integrations.mt5.metrics');

Route::post('/mt5/accounts/{accountIdentifier}/metrics', TradingAccountMetricsController::class)
    ->name('api.mt5.metrics');
