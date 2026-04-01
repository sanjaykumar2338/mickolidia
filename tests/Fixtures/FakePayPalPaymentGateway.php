<?php

namespace Tests\Fixtures;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;

class FakePayPalPaymentGateway implements PaymentGatewayInterface
{
    public function provider(): string
    {
        return 'paypal';
    }

    public function createCheckoutSession(Order $order, array $context = []): array
    {
        return [
            'provider' => $this->provider(),
            'checkout_url' => 'https://paypal.test/checkout/fake-paypal-order-'.$order->id,
            'external_checkout_id' => 'fake-paypal-order-'.$order->id,
            'external_payment_id' => null,
            'external_customer_id' => 'fake-paypal-customer-'.$order->id,
            'amount' => (float) $order->final_price,
            'currency' => $order->currency,
            'status' => 'pending',
            'payload' => [
                'fake' => true,
                'success_url' => $context['success_url'] ?? null,
                'cancel_url' => $context['cancel_url'] ?? null,
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
            'external_payment_id' => 'fake-paypal-capture-'.$order->id,
            'external_customer_id' => 'fake-paypal-customer-'.$order->id,
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

    /**
     * @return array<string, mixed>
     */
    public function captureOrder(string $externalCheckoutId): array
    {
        return $this->retrieveCheckoutSession($externalCheckoutId);
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyPayment(string $externalCheckoutId): array
    {
        return $this->retrieveCheckoutSession($externalCheckoutId);
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public function handleWebhook(string $payload, array $headers = []): array
    {
        return $this->parseWebhook($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function refund(Order $order, ?float $amount = null): array
    {
        return [
            'provider' => $this->provider(),
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'external_checkout_id' => $order->external_checkout_id,
            'external_payment_id' => $order->external_payment_id,
            'amount' => $amount ?? (float) $order->final_price,
            'currency' => $order->currency,
            'status' => 'completed',
            'payload' => [
                'fake' => true,
            ],
            'source' => 'refund',
        ];
    }
}
