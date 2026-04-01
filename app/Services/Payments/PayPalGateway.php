<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\Models\Builders\AmountWithBreakdownBuilder;
use PaypalServerSdkLib\Models\Builders\MoneyBuilder;
use PaypalServerSdkLib\Models\Builders\OrderApplicationContextBuilder;
use PaypalServerSdkLib\Models\Builders\OrderRequestBuilder;
use PaypalServerSdkLib\Models\Builders\PurchaseUnitRequestBuilder;
use PaypalServerSdkLib\Models\Builders\RefundRequestBuilder;
use PaypalServerSdkLib\Models\CheckoutPaymentIntent;
use PaypalServerSdkLib\PaypalServerSdkClient;
use PaypalServerSdkLib\PaypalServerSdkClientBuilder;
use RuntimeException;
use Throwable;

class PayPalGateway implements PaymentGatewayInterface
{
    private ?PaypalServerSdkClient $client = null;

    public function provider(): string
    {
        return 'paypal';
    }

    public function createCheckoutSession(Order $order, array $context = []): array
    {
        $successUrl = $context['success_url'] ?? null;
        $cancelUrl = $context['cancel_url'] ?? null;

        if (! is_string($successUrl) || $successUrl === '') {
            throw new RuntimeException('PayPal success URL is required.');
        }

        if (! is_string($cancelUrl) || $cancelUrl === '') {
            throw new RuntimeException('PayPal cancel URL is required.');
        }

        try {
            $response = $this->client()->getOrdersController()->createOrder([
                'body' => OrderRequestBuilder::init(
                    CheckoutPaymentIntent::CAPTURE,
                    [
                        PurchaseUnitRequestBuilder::init(
                            AmountWithBreakdownBuilder::init(
                                strtoupper($order->currency),
                                $this->formatAmount((float) $order->final_price),
                            )->build()
                        )
                            ->referenceId($order->order_number)
                            ->invoiceId($order->order_number)
                            ->customId((string) $order->id)
                            ->description(sprintf(
                                '%s %dK Wolforix challenge',
                                $this->challengeTypeLabel($order->challenge_type),
                                (int) ($order->account_size / 1000),
                            ))
                            ->build(),
                    ]
                )
                    ->applicationContext(
                        OrderApplicationContextBuilder::init()
                            ->brandName(config('app.name'))
                            ->locale($this->paypalLocale())
                            ->landingPage('LOGIN')
                            ->shippingPreference('NO_SHIPPING')
                            ->userAction('PAY_NOW')
                            ->returnUrl($successUrl)
                            ->cancelUrl($cancelUrl)
                            ->build()
                    )
                    ->build(),
                'paypalRequestId' => $order->order_number,
                'prefer' => 'return=representation',
            ]);
        } catch (Throwable $exception) {
            $this->logFailure('create_order_failed', $exception, [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            throw new RuntimeException('PayPal checkout session could not be created.', previous: $exception);
        }

        $payload = $this->payloadArray($response->getResult());
        $normalized = $this->normalizeOrderPayload($payload, 'checkout_creation');
        $checkoutUrl = $this->resolveApprovalUrl($payload['links'] ?? []);

        if ($checkoutUrl === null) {
            Log::warning('paypal.create_order_missing_approval_link', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'paypal_order_id' => $payload['id'] ?? null,
            ]);

            throw new RuntimeException('PayPal approval link was not returned.');
        }

        return array_merge($normalized, [
            'checkout_url' => $checkoutUrl,
        ]);
    }

    public function retrieveCheckoutSession(string $externalCheckoutId): array
    {
        try {
            return $this->normalizeOrderPayload(
                $this->fetchOrderPayload($externalCheckoutId),
                'success_page',
            );
        } catch (Throwable $exception) {
            $this->logFailure('retrieve_order_failed', $exception, [
                'paypal_order_id' => $externalCheckoutId,
            ]);

            throw new RuntimeException('PayPal order details could not be retrieved.', previous: $exception);
        }
    }

    public function parseWebhook(string $payload, ?string $signature = null): array
    {
        throw new RuntimeException('PayPal webhook parsing requires the full webhook header set and is not wired to this endpoint.');
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public function handleWebhook(string $payload, array $headers = []): array
    {
        $webhookId = (string) config('services.paypal.webhook_id', '');

        if ($webhookId === '') {
            throw new RuntimeException('PayPal webhook ID is not configured.');
        }

        $headerMap = $this->normalizeHeaders($headers);
        $requiredHeaders = [
            'paypal-transmission-id',
            'paypal-transmission-time',
            'paypal-transmission-sig',
            'paypal-cert-url',
            'paypal-auth-algo',
        ];

        foreach ($requiredHeaders as $header) {
            if (! isset($headerMap[$header]) || $headerMap[$header] === '') {
                throw new RuntimeException(sprintf('PayPal webhook header [%s] is missing.', $header));
            }
        }

        $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        $verificationResponse = Http::acceptJson()
            ->timeout((int) config('services.paypal.timeout', 15))
            ->withBasicAuth(
                (string) config('services.paypal.client_id'),
                (string) config('services.paypal.client_secret'),
            )
            ->baseUrl((string) config('services.paypal.base_url'))
            ->post('/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $headerMap['paypal-auth-algo'],
                'cert_url' => $headerMap['paypal-cert-url'],
                'transmission_id' => $headerMap['paypal-transmission-id'],
                'transmission_sig' => $headerMap['paypal-transmission-sig'],
                'transmission_time' => $headerMap['paypal-transmission-time'],
                'webhook_id' => $webhookId,
                'webhook_event' => $event,
            ]);

        if (! $verificationResponse->successful()) {
            Log::warning('paypal.webhook_verification_failed', [
                'status' => $verificationResponse->status(),
                'body' => $verificationResponse->json(),
            ]);

            throw new RuntimeException('PayPal webhook verification request failed.');
        }

        if (($verificationResponse->json('verification_status') ?? '') !== 'SUCCESS') {
            throw new RuntimeException('PayPal webhook signature verification failed.');
        }

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyPayment(string $externalCheckoutId): array
    {
        return $this->retrieveCheckoutSession($externalCheckoutId);
    }

    /**
     * @return array<string, mixed>
     */
    public function captureOrder(string $externalCheckoutId): array
    {
        try {
            $existingPayload = $this->fetchOrderPayload($externalCheckoutId);

            if (strtoupper((string) ($existingPayload['status'] ?? '')) === 'COMPLETED') {
                return $this->normalizeOrderPayload($existingPayload, 'capture_page');
            }

            $response = $this->client()->getOrdersController()->captureOrder([
                'id' => $externalCheckoutId,
                'paypalRequestId' => 'capture-'.$externalCheckoutId,
                'prefer' => 'return=representation',
            ]);
        } catch (Throwable $exception) {
            $this->logFailure('capture_order_failed', $exception, [
                'paypal_order_id' => $externalCheckoutId,
            ]);

            throw new RuntimeException('PayPal order capture failed.', previous: $exception);
        }

        return $this->normalizeOrderPayload(
            $this->payloadArray($response->getResult()),
            'capture_page',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function refund(Order $order, ?float $amount = null): array
    {
        $captureId = (string) ($order->external_payment_id ?? '');

        if ($captureId === '') {
            throw new RuntimeException('PayPal capture ID is required before a refund can be issued.');
        }

        $refundRequest = RefundRequestBuilder::init()
            ->invoiceId($order->order_number)
            ->customId((string) $order->id)
            ->noteToPayer('Refund issued by Wolforix')
            ->build();

        if ($amount !== null) {
            $refundRequest = RefundRequestBuilder::init()
                ->amount(MoneyBuilder::init(
                    strtoupper($order->currency),
                    $this->formatAmount($amount),
                )->build())
                ->invoiceId($order->order_number)
                ->customId((string) $order->id)
                ->noteToPayer('Refund issued by Wolforix')
                ->build();
        }

        try {
            $response = $this->client()->getPaymentsController()->refundCapturedPayment([
                'captureId' => $captureId,
                'paypalRequestId' => 'refund-'.$order->order_number,
                'prefer' => 'return=representation',
                'body' => $refundRequest,
            ]);
        } catch (Throwable $exception) {
            $this->logFailure('refund_failed', $exception, [
                'order_id' => $order->id,
                'paypal_capture_id' => $captureId,
            ]);

            throw new RuntimeException('PayPal refund failed.', previous: $exception);
        }

        $payload = $this->payloadArray($response->getResult());

        return [
            'provider' => $this->provider(),
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'external_checkout_id' => $order->external_checkout_id,
            'external_payment_id' => $captureId,
            'amount' => (float) ($payload['amount']['value'] ?? $order->final_price),
            'currency' => strtoupper((string) ($payload['amount']['currency_code'] ?? $order->currency)),
            'status' => strtolower((string) ($payload['status'] ?? 'refunded')),
            'payload' => $payload,
            'source' => 'refund',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchOrderPayload(string $externalCheckoutId): array
    {
        $response = $this->client()->getOrdersController()->getOrder([
            'id' => $externalCheckoutId,
        ]);

        return $this->payloadArray($response->getResult());
    }

    private function client(): PaypalServerSdkClient
    {
        if ($this->client instanceof PaypalServerSdkClient) {
            return $this->client;
        }

        $clientId = (string) config('services.paypal.client_id', '');
        $clientSecret = (string) config('services.paypal.client_secret', '');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('PayPal credentials are not configured.');
        }

        $environment = strtolower((string) config('services.paypal.mode', 'sandbox')) === 'live'
            ? Environment::PRODUCTION
            : Environment::SANDBOX;

        $this->client = PaypalServerSdkClientBuilder::init()
            ->clientCredentialsAuthCredentials(
                ClientCredentialsAuthCredentialsBuilder::init($clientId, $clientSecret)
            )
            ->environment($environment)
            ->timeout((int) config('services.paypal.timeout', 15))
            ->enableRetries(true)
            ->numberOfRetries(1)
            ->build();

        return $this->client;
    }

    /**
     * @param  object|array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payloadArray(object|array $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        return json_decode(
            json_encode($payload, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeOrderPayload(array $payload, string $source): array
    {
        $purchaseUnit = $payload['purchase_units'][0] ?? [];
        $capture = $this->latestCapture($purchaseUnit);
        $amount = $capture['amount'] ?? $purchaseUnit['amount'] ?? [];
        $orderId = $purchaseUnit['custom_id'] ?? null;
        $orderNumber = $purchaseUnit['invoice_id'] ?? $purchaseUnit['reference_id'] ?? null;

        return [
            'provider' => $this->provider(),
            'order_id' => is_string($orderId) && ctype_digit($orderId) ? (int) $orderId : null,
            'order_number' => is_string($orderNumber) ? $orderNumber : null,
            'checkout_url' => $this->resolveApprovalUrl($payload['links'] ?? []),
            'external_checkout_id' => $payload['id'] ?? null,
            'external_payment_id' => $capture['id'] ?? null,
            'external_customer_id' => data_get($payload, 'payer.payer_id') ?? data_get($payload, 'payer.email_address'),
            'amount' => (float) ($amount['value'] ?? 0),
            'currency' => strtoupper((string) ($amount['currency_code'] ?? 'USD')),
            'status' => $this->mapStatus(
                (string) ($capture['status'] ?? $payload['status'] ?? ''),
            ),
            'payload' => $payload,
            'source' => $source,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     */
    private function resolveApprovalUrl(array $links): ?string
    {
        foreach ($links as $link) {
            $relation = strtolower((string) ($link['rel'] ?? ''));

            if (in_array($relation, ['approve', 'payer-action'], true)) {
                return (string) ($link['href'] ?? '');
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $purchaseUnit
     * @return array<string, mixed>
     */
    private function latestCapture(array $purchaseUnit): array
    {
        $captures = data_get($purchaseUnit, 'payments.captures', []);

        if (! is_array($captures) || $captures === []) {
            return [];
        }

        $latestCapture = end($captures);

        return is_array($latestCapture) ? $latestCapture : [];
    }

    private function mapStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'COMPLETED' => 'paid',
            'VOIDED' => 'canceled',
            'DENIED', 'FAILED', 'DECLINED' => 'failed',
            'APPROVED', 'CREATED', 'PAYER_ACTION_REQUIRED', 'SAVED' => 'pending',
            default => Str::lower($status ?: 'pending'),
        };
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function paypalLocale(): string
    {
        return match (app()->getLocale()) {
            'de' => 'de-DE',
            'es' => 'es-ES',
            'fr' => 'fr-FR',
            default => 'en-US',
        };
    }

    private function challengeTypeLabel(string $challengeType): string
    {
        return (string) config(
            'wolforix.challenge_catalog.'.$challengeType.'.label',
            $challengeType === 'one_step' ? '1-Step Instant' : '2-Step Pro',
        );
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $value) {
            $normalizedKey = Str::lower((string) $key);

            if (is_array($value)) {
                $value = $value[0] ?? '';
            }

            $normalized[$normalizedKey] = is_string($value) ? $value : '';
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logFailure(string $event, Throwable $exception, array $context = []): void
    {
        Log::warning('paypal.'.$event, array_merge($context, [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ]));
    }
}
