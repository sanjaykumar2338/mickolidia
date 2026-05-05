<?php

namespace Tests\Fixtures;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;

class FakeStripePaymentGateway implements PaymentGatewayInterface
{
    public static int $checkoutSessionsCreated = 0;

    public function provider(): string
    {
        return 'stripe';
    }

    public function createCheckoutSession(Order $order, array $context = []): array
    {
        self::$checkoutSessionsCreated++;

        return [
            'provider' => $this->provider(),
            'checkout_url' => 'https://stripe.test/checkout/fake-session-'.$order->id,
            'external_checkout_id' => 'fake-session-'.$order->id,
            'external_payment_id' => 'fake-payment-'.$order->id,
            'external_customer_id' => 'fake-customer-'.$order->id,
            'amount' => (float) $order->final_price,
            'currency' => $order->currency,
            'payload' => [
                'fake' => true,
            ],
        ];
    }

    public function retrieveCheckoutSession(string $externalCheckoutId): array
    {
        preg_match('/(\d+)$/', $externalCheckoutId, $matches);
        $orderId = isset($matches[1]) ? (int) $matches[1] : 0;
        $order = Order::query()->findOrFail($orderId);

        return [
            'provider' => $this->provider(),
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'external_checkout_id' => $externalCheckoutId,
            'external_payment_id' => 'fake-payment-'.$order->id,
            'external_customer_id' => 'fake-customer-'.$order->id,
            'amount' => (float) $order->final_price,
            'currency' => $order->currency,
            'status' => 'paid',
            'payload' => [
                'fake' => true,
            ],
            'source' => 'success_page',
        ];
    }

    public function parseWebhook(string $payload, ?string $signature = null): array
    {
        return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    }
}
