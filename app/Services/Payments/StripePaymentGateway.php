<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripePaymentGateway implements PaymentGatewayInterface
{
    private ?StripeClient $client = null;

    public function provider(): string
    {
        return 'stripe';
    }

    public function createCheckoutSession(Order $order, array $context = []): array
    {
        $challengeLabel = $order->challenge_type === 'one_step'
            ? '1-Step Challenge'
            : '2-Step Challenge';

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'success_url' => $context['success_url'] ?? throw new RuntimeException('Stripe success URL is required.'),
            'cancel_url' => $context['cancel_url'] ?? throw new RuntimeException('Stripe cancel URL is required.'),
            'customer_email' => $order->email,
            'billing_address_collection' => 'required',
            'payment_intent_data' => [
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'order_number' => $order->order_number,
                    'challenge_type' => $order->challenge_type,
                    'account_size' => (string) $order->account_size,
                    'payment_provider' => $this->provider(),
                    'user_id' => (string) ($order->user_id ?? ''),
                ],
            ],
            'metadata' => [
                'order_id' => (string) $order->id,
                'order_number' => $order->order_number,
                'challenge_type' => $order->challenge_type,
                'account_size' => (string) $order->account_size,
                'payment_provider' => $this->provider(),
                'user_id' => (string) ($order->user_id ?? ''),
            ],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'unit_amount' => (int) round(((float) $order->final_price) * 100),
                    'product_data' => [
                        'name' => sprintf('%s %dK', $challengeLabel, (int) ($order->account_size / 1000)),
                        'description' => 'Wolforix simulated trading evaluation',
                    ],
                ],
            ]],
        ]);

        return [
            'provider' => $this->provider(),
            'checkout_url' => $session->url,
            'external_checkout_id' => $session->id,
            'external_payment_id' => $session->payment_intent,
            'external_customer_id' => $session->customer,
            'amount' => ((int) $session->amount_total) / 100,
            'currency' => strtoupper((string) $session->currency),
            'payload' => $session->toArray(),
        ];
    }

    public function retrieveCheckoutSession(string $externalCheckoutId): array
    {
        /** @var Session $session */
        $session = $this->client()->checkout->sessions->retrieve($externalCheckoutId, [
            'expand' => ['payment_intent', 'customer'],
        ]);

        return [
            'provider' => $this->provider(),
            'order_id' => isset($session->metadata['order_id']) ? (int) $session->metadata['order_id'] : null,
            'order_number' => $session->metadata['order_number'] ?? null,
            'external_checkout_id' => $session->id,
            'external_payment_id' => $session->payment_intent?->id ?? $session->payment_intent,
            'external_customer_id' => $session->customer?->id ?? $session->customer,
            'amount' => ((int) $session->amount_total) / 100,
            'currency' => strtoupper((string) $session->currency),
            'status' => $session->payment_status === 'paid' ? 'paid' : (string) $session->status,
            'payload' => $session->toArray(),
            'source' => 'success_page',
        ];
    }

    public function parseWebhook(string $payload, ?string $signature = null): array
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            throw new RuntimeException('Stripe webhook secret is not configured.');
        }

        try {
            $event = Webhook::constructEvent($payload, (string) $signature, $secret);
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            throw new RuntimeException('Stripe webhook signature verification failed.', previous: $exception);
        }

        $object = $event->data->object;

        return match ($event->type) {
            'checkout.session.completed' => [
                'provider' => $this->provider(),
                'event_id' => $event->id,
                'type' => $event->type,
                'order_id' => isset($object->metadata['order_id']) ? (int) $object->metadata['order_id'] : null,
                'order_number' => $object->metadata['order_number'] ?? null,
                'external_checkout_id' => $object->id,
                'external_payment_id' => $object->payment_intent,
                'external_customer_id' => $object->customer,
                'amount' => ((int) $object->amount_total) / 100,
                'currency' => strtoupper((string) $object->currency),
                'status' => 'paid',
                'payload' => $event->toArray(),
                'source' => 'webhook',
            ],
            'payment_intent.payment_failed' => [
                'provider' => $this->provider(),
                'event_id' => $event->id,
                'type' => $event->type,
                'order_id' => isset($object->metadata['order_id']) ? (int) $object->metadata['order_id'] : null,
                'order_number' => $object->metadata['order_number'] ?? null,
                'external_checkout_id' => null,
                'external_payment_id' => $object->id,
                'external_customer_id' => $object->customer,
                'amount' => ((int) $object->amount) / 100,
                'currency' => strtoupper((string) $object->currency),
                'status' => 'failed',
                'payload' => $event->toArray(),
                'source' => 'webhook',
            ],
            'checkout.session.expired' => [
                'provider' => $this->provider(),
                'event_id' => $event->id,
                'type' => $event->type,
                'order_id' => isset($object->metadata['order_id']) ? (int) $object->metadata['order_id'] : null,
                'order_number' => $object->metadata['order_number'] ?? null,
                'external_checkout_id' => $object->id,
                'external_payment_id' => $object->payment_intent,
                'external_customer_id' => $object->customer,
                'amount' => ((int) $object->amount_total) / 100,
                'currency' => strtoupper((string) $object->currency),
                'status' => 'canceled',
                'payload' => $event->toArray(),
                'source' => 'webhook',
            ],
            default => throw new RuntimeException("Unsupported Stripe webhook event [{$event->type}]."),
        };
    }

    private function client(): StripeClient
    {
        if ($this->client instanceof StripeClient) {
            return $this->client;
        }

        $secret = (string) config('services.stripe.secret');

        if ($secret === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        $this->client = new StripeClient($secret);

        return $this->client;
    }
}
