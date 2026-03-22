<?php

namespace App\Contracts;

use App\Models\Order;

interface PaymentGatewayInterface
{
    public function provider(): string;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function createCheckoutSession(Order $order, array $context = []): array;

    /**
     * @return array<string, mixed>
     */
    public function retrieveCheckoutSession(string $externalCheckoutId): array;

    /**
     * @return array<string, mixed>
     */
    public function parseWebhook(string $payload, ?string $signature = null): array;
}
