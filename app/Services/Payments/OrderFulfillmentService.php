<?php

namespace App\Services\Payments;

use App\Jobs\SendChallengePurchaseConfirmation;
use App\Models\ChallengePlan;
use App\Models\ChallengePurchase;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderFulfillmentService
{
    /**
     * @param  array<string, mixed>  $paymentData
     */
    public function markPaid(Order $order, array $paymentData): ChallengePurchase
    {
        return DB::transaction(function () use ($order, $paymentData): ChallengePurchase {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->with(['challengePurchase', 'user'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $lockedOrder->forceFill([
                'payment_status' => Order::PAYMENT_PAID,
                'order_status' => Order::STATUS_COMPLETED,
                'external_checkout_id' => $paymentData['external_checkout_id'] ?? $lockedOrder->external_checkout_id,
                'external_payment_id' => $paymentData['external_payment_id'] ?? $lockedOrder->external_payment_id,
                'external_customer_id' => $paymentData['external_customer_id'] ?? $lockedOrder->external_customer_id,
                'metadata' => array_merge($lockedOrder->metadata ?? [], [
                    'last_payment_sync' => now()->toIso8601String(),
                    'payment_sync_source' => $paymentData['source'] ?? 'provider',
                ]),
            ])->save();

            $this->syncPaymentAttempt($lockedOrder, $paymentData, 'completed');

            $user = $this->resolveOrderUser($lockedOrder);
            $plan = $this->resolveChallengePlan($lockedOrder);

            $purchase = ChallengePurchase::query()->firstOrCreate(
                ['order_id' => $lockedOrder->id],
                [
                    'user_id' => $user->id,
                    'challenge_plan_id' => $plan?->id,
                    'challenge_type' => $lockedOrder->challenge_type,
                    'account_size' => $lockedOrder->account_size,
                    'currency' => $lockedOrder->currency,
                    'account_status' => 'pending_activation',
                    'funded_status' => null,
                    'started_at' => null,
                    'meta' => [
                        'payment_provider' => $lockedOrder->payment_provider,
                        'billing_email' => $lockedOrder->email,
                    ],
                ],
            );

            $lockedOrder->forceFill([
                'user_id' => $user->id,
                'challenge_plan_id' => $plan?->id,
            ])->save();

            $this->syncUserSnapshot($user, $lockedOrder);

            if ($purchase->wasRecentlyCreated) {
                SendChallengePurchaseConfirmation::dispatch($lockedOrder->id);
            }

            return $purchase;
        });
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public function markFailed(Order $order, array $paymentData): void
    {
        DB::transaction(function () use ($order, $paymentData): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $lockedOrder->forceFill([
                'payment_status' => Order::PAYMENT_FAILED,
                'order_status' => Order::STATUS_AWAITING_PAYMENT,
                'external_checkout_id' => $paymentData['external_checkout_id'] ?? $lockedOrder->external_checkout_id,
                'external_payment_id' => $paymentData['external_payment_id'] ?? $lockedOrder->external_payment_id,
                'external_customer_id' => $paymentData['external_customer_id'] ?? $lockedOrder->external_customer_id,
            ])->save();

            $this->syncPaymentAttempt($lockedOrder, $paymentData, 'failed');
        });
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public function markCanceled(Order $order, array $paymentData = []): void
    {
        DB::transaction(function () use ($order, $paymentData): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $lockedOrder->forceFill([
                'payment_status' => Order::PAYMENT_CANCELED,
                'order_status' => Order::STATUS_CANCELED,
                'external_checkout_id' => $paymentData['external_checkout_id'] ?? $lockedOrder->external_checkout_id,
                'external_payment_id' => $paymentData['external_payment_id'] ?? $lockedOrder->external_payment_id,
            ])->save();

            $this->syncPaymentAttempt($lockedOrder, $paymentData, 'canceled');
        });
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public function syncPaymentAttempt(Order $order, array $paymentData, string $status): PaymentAttempt
    {
        $provider = strtolower((string) ($paymentData['provider'] ?? $order->payment_provider));
        $sessionId = $paymentData['external_checkout_id'] ?? null;

        $attempt = $order->paymentAttempts()
            ->when($sessionId, fn ($query) => $query->where('provider_session_id', $sessionId))
            ->latest('id')
            ->first();

        if (! $attempt instanceof PaymentAttempt) {
            $attempt = new PaymentAttempt([
                'provider' => $provider,
            ]);
            $attempt->order()->associate($order);
        }

        $attempt->fill([
            'provider' => $provider,
            'provider_session_id' => $sessionId,
            'provider_payment_id' => $paymentData['external_payment_id'] ?? $attempt->provider_payment_id,
            'amount' => (float) ($paymentData['amount'] ?? $order->final_price),
            'currency' => strtoupper((string) ($paymentData['currency'] ?? $order->currency)),
            'status' => $status,
            'payload' => $paymentData['payload'] ?? null,
        ]);

        $attempt->save();

        return $attempt;
    }

    private function resolveOrderUser(Order $order): User
    {
        $user = $order->user_id !== null
            ? $order->user()->first()
            : null;

        if (! $user instanceof User) {
            $user = User::query()->where('email', $order->email)->first();

            if (! $user instanceof User) {
                $user = User::query()->create([
                    'name' => $order->full_name,
                    'email' => $order->email,
                    'password' => Str::password(32),
                    'status' => 'active',
                ]);
            }
        }

        UserProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'preferred_language' => $order->metadata['locale'] ?? app()->getLocale(),
                'country' => $this->countryName($order->country),
                'city' => $order->city,
                'street_address' => $order->street_address,
                'postal_code' => $order->postal_code,
            ],
        );

        return $user;
    }

    private function resolveChallengePlan(Order $order): ?ChallengePlan
    {
        $slug = str_replace('_', '-', $order->challenge_type).'-'.$order->account_size;

        return ChallengePlan::query()->where('slug', $slug)->first();
    }

    private function syncUserSnapshot(User $user, Order $order): void
    {
        $label = $this->challengeTypeLabel($order->challenge_type);

        $user->forceFill([
            'plan_type' => $label,
            'account_size' => $order->account_size,
            'payment_amount' => $order->final_price,
            'status' => 'active',
        ])->save();
    }

    private function countryName(string $countryCode): string
    {
        return config("wolforix.checkout_countries.{$countryCode}", $countryCode);
    }

    private function challengeTypeLabel(string $challengeType): string
    {
        return (string) config(
            'wolforix.challenge_catalog.'.$challengeType.'.label',
            $challengeType === 'one_step' ? '1-Step Instant' : '2-Step Pro',
        );
    }
}
