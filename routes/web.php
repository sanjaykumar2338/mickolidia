<?php

use App\Http\Controllers\AdminClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;

Route::post('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');
Route::post('/challenge-checkout', [PublicPageController::class, 'storeChallengeCheckout'])->name('challenge.checkout.store');

Route::view('/login', 'public.login')->name('login');
Route::get('/', [PublicPageController::class, 'home'])->name('home');
Route::get('/faq', [PublicPageController::class, 'faq'])->name('faq');
Route::get('/terms', [PublicPageController::class, 'legal'])->defaults('slug', 'terms')->name('terms');
Route::get('/risk-disclosure', [PublicPageController::class, 'legal'])->defaults('slug', 'risk-disclosure')->name('risk-disclosure');
Route::get('/payout-policy', [PublicPageController::class, 'legal'])->defaults('slug', 'payout-policy')->name('payout-policy');
Route::get('/refund-policy', [PublicPageController::class, 'legal'])->defaults('slug', 'refund-policy')->name('refund-policy');
Route::get('/privacy-policy', [PublicPageController::class, 'legal'])->defaults('slug', 'privacy-policy')->name('privacy-policy');
Route::get('/aml-kyc', [PublicPageController::class, 'legal'])->defaults('slug', 'aml-kyc')->name('aml-kyc');
Route::get('/company-info', [PublicPageController::class, 'legal'])->defaults('slug', 'company-info')->name('company-info');

Route::prefix('dashboard')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/accounts', [DashboardController::class, 'accounts'])->name('dashboard.accounts');
    Route::get('/payouts', [DashboardController::class, 'payouts'])->name('dashboard.payouts');
    Route::get('/settings', [DashboardController::class, 'settings'])->name('dashboard.settings');
});

Route::middleware('admin.basic')->prefix('admin')->group(function (): void {
    Route::get('/clients', [AdminClientController::class, 'index'])->name('admin.clients.index');
    Route::get('/client/{user}', [AdminClientController::class, 'show'])->name('admin.clients.show');
});
