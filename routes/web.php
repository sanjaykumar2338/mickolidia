<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminClientController;
use App\Http\Controllers\AdminReviewRequestController;
use App\Http\Controllers\CTraderAuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardCertificateController;
use App\Http\Controllers\DashboardInvoiceController;
use App\Http\Controllers\DashboardWolfiController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PayPalController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TrialController;
use App\Http\Controllers\VoiceAssistantSpeechController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');
Route::post('/launch-offer', [PublicPageController::class, 'updateLaunchOffer'])->name('launch-offer.update');
Route::middleware('auth')->group(function (): void {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/order', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::post('/checkout/promo-preview', [CheckoutController::class, 'previewPromo'])->name('checkout.promo.preview');
    Route::post('/challenge-checkout', [CheckoutController::class, 'store'])->name('challenge.checkout.store');
    Route::get('/auth/ctrader/connect', [CTraderAuthController::class, 'redirect'])->name('ctrader.auth.connect');
    Route::get('/auth/ctrader/redirect', [CTraderAuthController::class, 'redirect'])->name('ctrader.auth.redirect');
    Route::get('/auth/callback', [CTraderAuthController::class, 'callback'])->name('ctrader.auth.callback');
    Route::post('/auth/ctrader/link-account', [CTraderAuthController::class, 'linkAccount'])->name('ctrader.auth.link-account');
});
Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
Route::get('/checkout/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
Route::get('/paypal/success', [PayPalController::class, 'success'])->name('paypal.success');
Route::get('/paypal/cancel', [PayPalController::class, 'cancel'])->name('paypal.cancel');
Route::post('/payments/stripe/webhook', StripeWebhookController::class)->name('payments.stripe.webhook');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->where('provider', 'google|facebook|apple')
        ->name('social.redirect');
    Route::match(['get', 'post'], '/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->where('provider', 'google|facebook|apple')
        ->name('social.callback');
    Route::get('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/', [PublicPageController::class, 'home'])->name('home');
Route::get('/challenges', static fn () => redirect()->to(route('home').'#plans'))->name('challenges.index');
Route::get('/about', [PublicPageController::class, 'about'])->name('about');
Route::get('/security', [PublicPageController::class, 'security'])->name('security');
Route::get('/contact', [PublicPageController::class, 'contact'])->name('contact');
Route::get('/faq', [PublicPageController::class, 'faq'])->name('faq');
Route::get('/news', [NewsController::class, 'index'])->name('news');
Route::post('/assistant/speech', VoiceAssistantSpeechController::class)
    ->middleware('throttle:20,1')
    ->name('assistant.speech');
Route::get('/trial/register', [TrialController::class, 'create'])->name('trial.register');
Route::post('/trial/register', [TrialController::class, 'store'])->name('trial.store');
Route::get('/terms', [PublicPageController::class, 'legal'])->defaults('slug', 'terms')->name('terms');
Route::get('/risk-disclosure', [PublicPageController::class, 'legal'])->defaults('slug', 'risk-disclosure')->name('risk-disclosure');
Route::get('/payout-policy', [PublicPageController::class, 'legal'])->defaults('slug', 'payout-policy')->name('payout-policy');
Route::get('/refund-policy', [PublicPageController::class, 'legal'])->defaults('slug', 'refund-policy')->name('refund-policy');
Route::get('/privacy-policy', [PublicPageController::class, 'legal'])->defaults('slug', 'privacy-policy')->name('privacy-policy');
Route::get('/aml-kyc', [PublicPageController::class, 'legal'])->defaults('slug', 'aml-kyc')->name('aml-kyc');
Route::get('/company-info', [PublicPageController::class, 'legal'])->defaults('slug', 'company-info')->name('company-info');

Route::middleware('auth')->prefix('dashboard')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/accounts', [DashboardController::class, 'accounts'])->name('dashboard.accounts');
    Route::get('/payouts', [DashboardController::class, 'payouts'])->name('dashboard.payouts');
    Route::get('/settings', [DashboardController::class, 'settings'])->name('dashboard.settings');
    Route::get('/wolfi', [DashboardController::class, 'wolfi'])->name('dashboard.wolfi');
    Route::post('/wolfi/respond', DashboardWolfiController::class)
        ->middleware('throttle:30,1')
        ->name('dashboard.wolfi.respond');
    Route::get('/certificates/{account}/download', DashboardCertificateController::class)->name('dashboard.certificates.download');
    Route::get('/invoices/{invoice}/download', DashboardInvoiceController::class)->name('dashboard.invoices.download');
});

Route::middleware('trial.session')->prefix('trial')->group(function (): void {
    Route::get('/dashboard', [TrialController::class, 'dashboard'])->name('trial.dashboard');
    Route::post('/retry', [TrialController::class, 'retry'])->name('trial.retry');
});

Route::prefix('admin')->group(function (): void {
    Route::get('/login', [AdminAuthController::class, 'create'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'store'])->name('admin.login.store');

    Route::middleware('admin.basic')->group(function (): void {
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('admin.logout');
        Route::get('/clients', [AdminClientController::class, 'index'])->name('admin.clients.index');
        Route::post('/client/{user}/activate', [AdminClientController::class, 'activate'])->name('admin.clients.activate');
        Route::post('/client/{user}/credentials', [AdminClientController::class, 'updateCredentials'])->name('admin.clients.credentials');
        Route::get('/client/{user}', [AdminClientController::class, 'show'])->name('admin.clients.show');
        Route::get('/reviews', [AdminReviewRequestController::class, 'index'])->name('admin.reviews.index');
        Route::post('/reviews/test', [AdminReviewRequestController::class, 'sendTest'])->name('admin.reviews.test');
        Route::post('/reviews/reminders/run', [AdminReviewRequestController::class, 'sendDueReminders'])->name('admin.reviews.reminders.run');
    });
});
