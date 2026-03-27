<?php

namespace App\Http\Controllers;

use App\Models\ChallengePlan;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\OrderFulfillmentService;
use App\Services\Payments\PaymentManager;
use App\Services\Pricing\ChallengePricingService;
use InvalidArgumentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
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
        $launchPromoCodeInput = $request->string('promo_code')->toString();

        if ($launchPromoCodeInput === '') {
            $launchPromoCodeInput = (string) data_get($retryOrder?->metadata, 'launch_promo.code', '');
        }

        return view('checkout.index', [
            'order' => $retryOrder,
            'selectedPlan' => $pricingService->resolvePlan($selectedType, $selectedSize, $selectedCurrency),
            'selectedChallengeType' => $selectedType,
            'selectedAccountSize' => $selectedSize,
            'selectedCurrency' => $selectedCurrency,
            'launchPromoCode' => $this->normalizeLaunchPromoCode($launchPromoCodeInput),
            'launchPromoCodeInput' => $launchPromoCodeInput,
            'checkoutCountries' => config('wolforix.checkout_countries', []),
            'paymentProviders' => $paymentManager->providers(),
        ]);
    }

    public function store(
        Request $request,
        ChallengePricingService $pricingService,
        PaymentManager $paymentManager,
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

        try {
            $selectedPlan = $pricingService->resolvePlan(
                $validated['challenge_type'],
                (int) $validated['account_size'],
                $validated['currency'],
            );
        } catch (InvalidArgumentException) {
            return back()
                ->withInput()
                ->withErrors([
                    'account_size' => __('validation.in'),
                ]);
        }

        $launchPromoCode = $this->normalizeLaunchPromoCode($validated['promo_code'] ?? null);

        if (($validated['promo_code'] ?? null) !== null && trim((string) $validated['promo_code']) !== '' && $launchPromoCode === null) {
            return back()
                ->withInput()
                ->withErrors([
                    'promo_code' => __('site.checkout.validation.promo_code'),
                ]);
        }

        $provider = $paymentManager->provider($validated['payment_provider']);
        $challengePlan = $this->resolveChallengePlanRecord($selectedPlan);
        $order = DB::transaction(function () use ($validated, $selectedPlan, $request, $challengePlan, $launchPromoCode): Order {
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
                : new Order();

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
                'payment_provider' => $validated['payment_provider'],
                'base_price' => $selectedPlan['list_price'],
                'discount_percent' => $selectedPlan['discount']['percent'],
                'discount_amount' => $selectedPlan['discount']['amount'],
                'final_price' => $selectedPlan['discounted_price'],
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
                ]),
            ]);
            $order->save();

            $order->paymentAttempts()->create([
                'provider' => $validated['payment_provider'],
                'amount' => $selectedPlan['discounted_price'],
                'currency' => $validated['currency'],
                'status' => 'pending',
                'payload' => [
                    'created_via' => 'checkout_form',
                ],
            ]);

            return $order;
        });

        $successUrl = route('checkout.success').'?provider='.$validated['payment_provider'].'&session_id={CHECKOUT_SESSION_ID}';

        try {
            $checkoutSession = $provider->createCheckoutSession($order, [
                'success_url' => $successUrl,
                'cancel_url' => route('checkout.cancel', ['order' => $order->order_number]),
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

        $session = $gateway->retrieveCheckoutSession($sessionId);
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

    /**
     * @param  array<string, mixed>  $selectedPlan
     */
    private function resolveChallengePlanRecord(array $selectedPlan): ?ChallengePlan
    {
        $basePlan = config('wolforix.challenge_catalog.'.$selectedPlan['challenge_type'].'.plans.'.$selectedPlan['account_size'], $selectedPlan);

        return ChallengePlan::query()->updateOrCreate(
            ['slug' => $selectedPlan['slug']],
            [
                'name' => $basePlan['name'],
                'account_size' => $basePlan['account_size'],
                'currency' => 'USD',
                'entry_fee' => $basePlan['entry_fee'],
                'profit_target' => $basePlan['profit_target'],
                'daily_loss_limit' => $basePlan['daily_loss_limit'],
                'max_loss_limit' => $basePlan['max_loss_limit'],
                'steps' => $basePlan['steps'],
                'profit_share' => $basePlan['profit_share'],
                'first_payout_days' => $basePlan['first_payout_days'],
                'minimum_trading_days' => $basePlan['minimum_trading_days'],
                'payout_cycle_days' => $basePlan['payout_cycle_days'],
                'is_active' => true,
            ],
        );
    }

    private function normalizeLaunchPromoCode(?string $promoCode): ?string
    {
        $promoCode = trim((string) $promoCode);
        $expectedCode = trim((string) config('wolforix.launch_discount.code', ''));

        if ($promoCode === '' || $expectedCode === '') {
            return null;
        }

        return strcasecmp($promoCode, $expectedCode) === 0
            ? $expectedCode
            : null;
    }
}
