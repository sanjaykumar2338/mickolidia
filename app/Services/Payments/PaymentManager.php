<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentManager
{
    public function provider(string $provider): PaymentGatewayInterface
    {
        $provider = strtolower($provider);
        $definition = config("wolforix.payments.providers.{$provider}");

        if (! is_array($definition)) {
            throw new InvalidArgumentException("Unsupported payment provider [{$provider}].");
        }

        $class = $definition['class'] ?? null;

        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidArgumentException("Payment provider [{$provider}] is not configured.");
        }

        /** @var PaymentGatewayInterface $gateway */
        $gateway = app($class);

        return $gateway;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function providers(bool $includeDisabled = true): array
    {
        $providers = config('wolforix.payments.providers', []);

        if ($includeDisabled) {
            return $providers;
        }

        return array_filter(
            $providers,
            fn (array $definition): bool => (bool) ($definition['enabled'] ?? false),
        );
    }

    /**
     * @return list<string>
     */
    public function enabledProviderKeys(): array
    {
        return array_keys($this->providers(false));
    }
}
