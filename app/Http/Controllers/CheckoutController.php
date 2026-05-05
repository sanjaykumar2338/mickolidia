<?php

namespace App\Http\Controllers;

use App\Models\ChallengePlan;
use App\Models\Mt5AccountPoolEntry;
use App\Models\Mt5PromoCode;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\OrderFulfillmentService;
use App\Services\Payments\PaymentManager;
use App\Services\Pricing\ChallengePricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class CheckoutController extends Controller
{
    public function show(Request $request, ChallengePricingService $pricingService, PaymentManager $paymentManager): View
    {
        $retryOrder = null;
        /** @var User $user */
        $user = $request->user();

        if ($request->filled('order')) {
            $retryOrder = Order::query()
                ->where('order_number', (string) $request->string('order'))
                ->where('user_id', $user->id)
                ->firstOrFail();
        }

        $selectedCurrency = $this->resolveCurrency(
            $request->string('currency')->toString() ?: $retryOrder?->currency,
            $pricingService,
        );
        $selectedType = $this->resolveChallengeType(
            $request->string('challenge_type')->toString() ?: $retryOrder?->challenge_type,
            $pricingService,
        );
        $selectedSize = $this->resolveAccountSize(
            $request->input('account_size', $retryOrder?->account_size),
            $selectedType,
            $pricingService,
        );
        $oldInput = $request->hasSession() ? $request->session()->getOldInput() : [];
        $hasOldPromoCodeInput = is_array($oldInput) && array_key_exists('promo_code', $oldInput);
        $launchPromoCodeInput = $hasOldPromoCodeInput
            ? trim((string) ($oldInput['promo_code'] ?? ''))
            : trim($request->string('promo_code')->toString());

        if ($launchPromoCodeInput === '') {
            $launchPromoCodeInput = trim((string) data_get($retryOrder?->metadata, 'launch_promo.code', ''));
        }

        $launchPromoCode = $pricingService->normalizeLaunchPromoCode($launchPromoCodeInput);

        if (! $hasOldPromoCodeInput && $launchPromoCode === null && $launchPromoCodeInput === '') {
            $launchPromoCode = $pricingService->launchPromoCodeForRequest($request);
            $launchPromoCodeInput = $launchPromoCode ?? '';
        }

        $launchDiscountApplied = $launchPromoCode !== null;

        return view('checkout.index', [
            'order' => $retryOrder,
            'basePlan' => $pricingService->resolvePlan($selectedType, $selectedSize, $selectedCurrency, false),
            'selectedPlan' => $pricingService->resolvePlan($selectedType, $selectedSize, $selectedCurrency, $launchDiscountApplied),
            'selectedChallengeType' => $selectedType,
            'selectedAccountSize' => $selectedSize,
            'selectedCurrency' => $selectedCurrency,
            'launchPromoCode' => $launchPromoCode,
            'launchPromoCodeInput' => $launchPromoCode ?? '',
            'checkoutCountries' => config('wolforix.checkout_countries', []),
            'paymentProviders' => $paymentManager->providers(),
        ]);
    }

    public function store(
        Request $request,
        ChallengePricingService $pricingService,
        PaymentManager $paymentManager,
        OrderFulfillmentService $fulfillmentService,
    ): RedirectResponse {
        $validated = $request->validate([
            'order' => ['nullable', 'string', 'exists:orders,order_number'],
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'street_address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:32'],
            'country' => ['required', Rule::in(array_keys(config('wolforix.checkout_countries', [])))],
            'challenge_type' => ['required', Rule::in(array_keys(config('wolforix.challenge_models', [])))],
            'account_size' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $challengeType = (string) $request->input('challenge_type');
                    $pricing = config("wolforix.challenge_models.{$challengeType}.pricing", []);

                    if (! array_key_exists((int) $value, $pricing)) {
                        $fail(__('validation.in'));
                    }
                },
            ],
            'currency' => ['required', Rule::in($pricingService->supportedProviderCurrencies())],
            'promo_code' => ['nullable', 'string', 'max:60'],
            'payment_provider' => ['required', Rule::in($paymentManager->enabledProviderKeys())],
            'accept_terms_and_residency' => ['accepted'],
            'accept_refund_policy' => ['accepted'],
        ], [
            'accept_terms_and_residency.accepted' => __('site.checkout.validation.accept_terms_and_residency'),
            'accept_refund_policy.accepted' => __('site.checkout.validation.accept_refund_policy'),
        ]);

        $promoCodeInput = trim((string) ($validated['promo_code'] ?? ''));
        $launchPromoCode = $pricingService->normalizeLaunchPromoCode($promoCodeInput);
        $giveawayPromoCode = $launchPromoCode === null
            ? $this->resolveGiveawayPromoCode($promoCodeInput)
            : null;

        if ($promoCodeInput !== '' && $launchPromoCode === null && ! $giveawayPromoCode instanceof Mt5PromoCode) {
            return back()
                ->withInput()
                ->withErrors([
                    'promo_code' => __('site.checkout.validation.promo_code'),
                ]);
        }

        $launchDiscountApplied = $launchPromoCode !== null;

        try {
            $selectedPlan = $pricingService->resolvePlan(
                $validated['challenge_type'],
                (int) $validated['account_size'],
                $validated['currency'],
                $launchDiscountApplied,
            );
        } catch (InvalidArgumentException) {
            return back()
                ->withInput()
                ->withErrors([
                    'account_size' => __('validation.in'),
                ]);
        }

        if ($giveawayPromoCode instanceof Mt5PromoCode) {
            $promoEntry = $giveawayPromoCode->poolEntry;

            if (
                ! $promoEntry instanceof Mt5AccountPoolEntry
                || ! $promoEntry->is_promo
                || $giveawayPromoCode->used_at !== null
                || $promoEntry->allocated_at !== null
                || $promoEntry->allocated_trading_account_id !== null
                || (int) $promoEntry->account_size !== (int) $validated['account_size']
            ) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'promo_code' => __('site.checkout.validation.promo_code'),
                    ]);
            }
        }

        $provider = $paymentManager->provider($validated['payment_provider']);
        $challengePlan = $this->resolveChallengePlanRecord($selectedPlan);
        $order = DB::transaction(function () use ($validated, $selectedPlan, $request, $challengePlan, $launchPromoCode, $giveawayPromoCode): Order {
            $existingOrder = null;
            $acceptedAt = now()->toIso8601String();
            /** @var User $user */
            $user = $request->user();

            if (! empty($validated['order'])) {
                $existingOrder = Order::query()
                    ->where('order_number', $validated['order'])
                    ->where('user_id', $user->id)
                    ->where('payment_status', '!=', Order::PAYMENT_PAID)
                    ->first();
            }

            $order = $existingOrder instanceof Order
                ? $existingOrder
                : new Order;

            $order->fill([
                'user_id' => $user->id,
                'challenge_plan_id' => $challengePlan?->id,
                'email' => $validated['email'],
                'full_name' => $validated['full_name'],
                'street_address' => $validated['street_address'],
                'city' => $validated['city'],
                'postal_code' => $validated['postal_code'],
                'country' => $validated['country'],
                'challenge_type' => $validated['challenge_type'],
                'account_size' => (int) $validated['account_size'],
                'currency' => $validated['currency'],
                'payment_provider' => $giveawayPromoCode instanceof Mt5PromoCode ? 'promo' : $validated['payment_provider'],
                'base_price' => $selectedPlan['list_price'],
                'discount_percent' => $giveawayPromoCode instanceof Mt5PromoCode ? 100 : $selectedPlan['discount']['percent'],
                'discount_amount' => $giveawayPromoCode instanceof Mt5PromoCode ? $selectedPlan['list_price'] : $selectedPlan['discount']['amount'],
                'final_price' => $giveawayPromoCode instanceof Mt5PromoCode ? 0 : $selectedPlan['discounted_price'],
                'payment_status' => Order::PAYMENT_PENDING,
                'order_status' => Order::STATUS_AWAITING_PAYMENT,
                'metadata' => array_merge($order->metadata ?? [], [
                    'locale' => app()->getLocale(),
                    'plan_slug' => $selectedPlan['slug'],
                    'launch_discount_enabled' => $selectedPlan['discount']['enabled'],
                    'checkout_confirmations' => [
                        'terms_and_residency' => [
                            'accepted' => true,
                            'accepted_at' => $acceptedAt,
                            'country' => $validated['country'],
                        ],
                        'refund_policy' => [
                            'accepted' => true,
                            'accepted_at' => $acceptedAt,
                        ],
                    ],
                    'launch_promo' => [
                        'code' => $launchPromoCode,
                        'campaign' => $launchPromoCode !== null ? 'launch_20' : null,
                        'applied' => $launchPromoCode !== null,
                    ],
                    'mt5_giveaway_promo' => $giveawayPromoCode instanceof Mt5PromoCode ? [
                        'id' => $giveawayPromoCode->id,
                        'code' => $giveawayPromoCode->code,
                        'mt5_account_pool_entry_id' => $giveawayPromoCode->mt5_account_pool_entry_id,
                        'mt5_login' => $giveawayPromoCode->mt5_login,
                        'applied' => true,
                    ] : null,
                ]),
            ]);
            $order->save();

            $order->paymentAttempts()->create([
                'provider' => $giveawayPromoCode instanceof Mt5PromoCode ? 'promo' : $validated['payment_provider'],
                'amount' => $giveawayPromoCode instanceof Mt5PromoCode ? 0 : $selectedPlan['discounted_price'],
                'currency' => $validated['currency'],
                'status' => 'pending',
                'payload' => [
                    'created_via' => 'checkout_form',
                ],
            ]);

            return $order;
        });

        if ($giveawayPromoCode instanceof Mt5PromoCode) {
            $fulfillmentService->markPaid($order, [
                'provider' => 'promo',
                'source' => 'mt5_giveaway_promo',
                'amount' => 0,
                'currency' => $order->currency,
                'payload' => [
                    'promo_code' => $giveawayPromoCode->code,
                    'mt5_login' => $giveawayPromoCode->mt5_login,
                ],
            ]);

            return redirect()->route('dashboard.accounts');
        }

        $successUrl = $validated['payment_provider'] === 'paypal'
            ? route('paypal.success', ['order' => $order->order_number])
            : route('checkout.success').'?provider='.$validated['payment_provider'].'&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $validated['payment_provider'] === 'paypal'
            ? route('paypal.cancel', ['order' => $order->order_number])
            : route('checkout.cancel', ['order' => $order->order_number]);

        try {
            $checkoutSession = $provider->createCheckoutSession($order, [
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'payment_provider' => __('site.checkout.errors.provider_unavailable'),
                ]);
        }

        $order->forceFill([
            'external_checkout_id' => $checkoutSession['external_checkout_id'] ?? $order->external_checkout_id,
            'external_payment_id' => $checkoutSession['external_payment_id'] ?? $order->external_payment_id,
            'external_customer_id' => $checkoutSession['external_customer_id'] ?? $order->external_customer_id,
        ])->save();

        $order->paymentAttempts()
            ->latest('id')
            ->first()?->update([
                'provider_session_id' => $checkoutSession['external_checkout_id'] ?? null,
                'provider_payment_id' => $checkoutSession['external_payment_id'] ?? null,
                'status' => 'redirected',
                'payload' => $checkoutSession['payload'] ?? null,
            ]);

        return redirect()->away((string) $checkoutSession['checkout_url']);
    }

    public function previewPromo(Request $request, ChallengePricingService $pricingService): JsonResponse
    {
        $validated = $request->validate([
            'challenge_type' => ['required', Rule::in(array_keys(config('wolforix.challenge_models', [])))],
            'account_size' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $challengeType = (string) $request->input('challenge_type');
                    $pricing = config("wolforix.challenge_models.{$challengeType}.pricing", []);

                    if (! array_key_exists((int) $value, $pricing)) {
                        $fail(__('validation.in'));
                    }
                },
            ],
            'currency' => ['required', Rule::in($pricingService->supportedProviderCurrencies())],
            'promo_code' => ['nullable', 'string', 'max:60'],
        ]);

        $promoCodeInput = trim((string) ($validated['promo_code'] ?? ''));
        $launchPromoCode = $pricingService->normalizeLaunchPromoCode($promoCodeInput);
        $giveawayPromoCode = $launchPromoCode === null
            ? $this->resolveGiveawayPromoCode($promoCodeInput)
            : null;
        $selectedPlan = $pricingService->resolvePlan(
            $validated['challenge_type'],
            (int) $validated['account_size'],
            $validated['currency'],
            $launchPromoCode !== null,
        );
        $giveawayApplies = $giveawayPromoCode instanceof Mt5PromoCode
            && $giveawayPromoCode->used_at === null
            && $giveawayPromoCode->poolEntry !== null
            && $giveawayPromoCode->poolEntry->is_promo
            && $giveawayPromoCode->poolEntry->allocated_at === null
            && $giveawayPromoCode->poolEntry->allocated_trading_account_id === null
            && (int) $giveawayPromoCode->poolEntry->account_size === (int) $validated['account_size'];

        if ($giveawayApplies) {
            $selectedPlan['discounted_price'] = 0;
            $selectedPlan['discount']['enabled'] = true;
            $selectedPlan['discount']['percent'] = 100;
            $selectedPlan['discount']['amount'] = $selectedPlan['list_price'];
        }

        return response()->json([
            'applied' => $launchPromoCode !== null || $giveawayApplies,
            'promo_code' => $giveawayApplies ? $giveawayPromoCode->code : $launchPromoCode,
            'message' => $promoCodeInput === ''
                ? ''
                : ($launchPromoCode !== null || $giveawayApplies
                    ? __('site.checkout.promo_code_feedback.success')
                    : __('site.checkout.promo_code_feedback.invalid')),
            'pricing' => $this->pricingPayload($selectedPlan),
        ]);
    }

    public function success(
        Request $request,
        PaymentManager $paymentManager,
        OrderFulfillmentService $fulfillmentService,
    ): View|RedirectResponse {
        $sessionId = (string) $request->query('session_id', '');
        $providerKey = strtolower((string) $request->query('provider', 'stripe'));

        abort_if($sessionId === '', 404);

        try {
            $gateway = $paymentManager->provider($providerKey);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        try {
            $session = $gateway->retrieveCheckoutSession($sessionId);
        } catch (Throwable $exception) {
            Log::warning('checkout.success_session_retrieval_failed', [
                'provider' => $providerKey,
                'session_id' => $sessionId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $order = Order::query()
                ->with(['challengePurchase', 'user'])
                ->where('external_checkout_id', $sessionId)
                ->first();

            if ($order instanceof Order && $order->isPaid()) {
                return view('checkout.success', [
                    'order' => $order,
                ]);
            }

            return redirect()
                ->route('checkout.cancel', ['order' => $order?->order_number])
                ->withErrors(['payment' => __('site.checkout.errors.provider_unavailable')]);
        }

        $order = Order::query()
            ->with(['challengePurchase', 'paymentAttempts'])
            ->where('external_checkout_id', $sessionId)
            ->orWhere('id', $session['order_id'] ?? 0)
            ->first();

        abort_unless($order instanceof Order, 404);

        if (($session['status'] ?? null) === 'paid') {
            $fulfillmentService->markPaid($order, $session);
            $order->refresh()->loadMissing(['challengePurchase', 'user']);
        }

        return view('checkout.success', [
            'order' => $order->fresh(['challengePurchase', 'user']),
        ]);
    }

    public function cancel(Request $request): View
    {
        $order = Order::query()
            ->where('order_number', (string) $request->query('order'))
            ->firstOrFail();

        if (! $order->isPaid()) {
            app(OrderFulfillmentService::class)->markCanceled($order, [
                'provider' => $order->payment_provider,
                'external_checkout_id' => $order->external_checkout_id,
                'external_payment_id' => $order->external_payment_id,
                'currency' => $order->currency,
                'amount' => (float) $order->final_price,
                'payload' => ['source' => 'cancel_page'],
            ]);
            $order->refresh();
        }

        return view('checkout.cancel', [
            'order' => $order,
        ]);
    }

    private function resolveCurrency(?string $currency, ChallengePricingService $pricingService): string
    {
        $currency = strtoupper((string) ($currency ?: config('wolforix.default_currency', 'USD')));

        return in_array($currency, $pricingService->supportedProviderCurrencies(), true)
            ? $currency
            : config('wolforix.default_currency', 'USD');
    }

    private function resolveChallengeType(?string $challengeType, ChallengePricingService $pricingService): string
    {
        $challengeType = $challengeType ?: $pricingService->defaultChallengeType();

        return array_key_exists((string) $challengeType, config('wolforix.challenge_models', []))
            ? (string) $challengeType
            : (string) $pricingService->defaultChallengeType();
    }

    private function resolveAccountSize(mixed $accountSize, string $challengeType, ChallengePricingService $pricingService): int
    {
        $pricing = config("wolforix.challenge_models.{$challengeType}.pricing", []);
        $size = (int) ($accountSize ?: $pricingService->defaultChallengeSize($challengeType));

        return array_key_exists($size, $pricing)
            ? $size
            : (int) $pricingService->defaultChallengeSize($challengeType);
    }

    private function resolveGiveawayPromoCode(string $promoCode): ?Mt5PromoCode
    {
        if ($promoCode === '') {
            return null;
        }

        return Mt5PromoCode::query()
            ->with('poolEntry')
            ->whereRaw('LOWER(code) = ?', [strtolower($promoCode)])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $selectedPlan
     */
    private function resolveChallengePlanRecord(array $selectedPlan): ?ChallengePlan
    {
        return ChallengePlan::query()->updateOrCreate(
            ['slug' => $selectedPlan['slug']],
            [
                'name' => $selectedPlan['name'],
                'account_size' => $selectedPlan['account_size'],
                'currency' => $selectedPlan['base_currency'] ?? 'USD',
                'entry_fee' => $selectedPlan['entry_fee'],
                'profit_target' => $selectedPlan['profit_target'],
                'daily_loss_limit' => $selectedPlan['daily_loss_limit'],
                'max_loss_limit' => $selectedPlan['max_loss_limit'],
                'steps' => $selectedPlan['steps'],
                'profit_share' => $selectedPlan['profit_share'],
                'first_payout_days' => $selectedPlan['first_payout_days'],
                'minimum_trading_days' => $selectedPlan['minimum_trading_days'],
                'payout_cycle_days' => $selectedPlan['payout_cycle_days'],
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function pricingPayload(array $plan): array
    {
        return [
            'currency' => $plan['currency'],
            'list_price' => number_format((float) $plan['list_price'], 2, '.', ''),
            'discounted_price' => number_format((float) $plan['discounted_price'], 2, '.', ''),
            'discount_enabled' => (bool) data_get($plan, 'discount.enabled', false),
            'discount_badge' => (string) data_get($plan, 'discount.badge', ''),
        ];
    }
}
