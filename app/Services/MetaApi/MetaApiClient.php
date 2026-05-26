<?php

namespace App\Services\MetaApi;

use DateTimeInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MetaApiClient
{
    public function isConfigured(): bool
    {
        return (bool) config('services.metaapi.enabled', false) && filled($this->token());
    }

    public function transactionId(): string
    {
        return Str::random(32);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createMt5DemoAccount(string $profileId, array $payload, ?string $transactionId = null): array
    {
        return $this->postProvisioning(
            path: '/users/current/provisioning-profiles/'.rawurlencode($profileId).'/mt5-demo-accounts',
            payload: $payload,
            action: 'create_mt5_demo_account',
            transactionId: $transactionId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createAccount(array $payload, ?string $transactionId = null): array
    {
        return $this->postProvisioning(
            path: '/users/current/accounts',
            payload: $payload,
            action: 'create_account',
            transactionId: $transactionId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function readAccount(string $accountId): array
    {
        return $this->getProvisioning(
            path: '/users/current/accounts/'.rawurlencode($accountId),
            query: [],
            action: 'read_account',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function readAccountReplicas(string $accountId): array
    {
        return $this->getProvisioning(
            path: '/users/current/accounts/'.rawurlencode($accountId).'/replicas',
            query: [],
            action: 'read_account_replicas',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function readAccounts(?string $query = null): array
    {
        return $this->getProvisioning(
            path: '/users/current/accounts',
            query: array_filter([
                'query' => filled($query) ? $query : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            action: 'read_accounts',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function deployAccount(string $accountId): array
    {
        return $this->postProvisioning(
            path: '/users/current/accounts/'.rawurlencode($accountId).'/deploy',
            payload: [],
            action: 'deploy_account',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function readAccountInformation(string $accountId, bool $refreshTerminalState = false): array
    {
        return $this->getClient(
            path: '/users/current/accounts/'.rawurlencode($accountId).'/account-information',
            query: ['refreshTerminalState' => $refreshTerminalState ? 'true' : 'false'],
            action: 'read_account_information',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function readPositions(string $accountId, bool $refreshTerminalState = false): array
    {
        return $this->getClient(
            path: '/users/current/accounts/'.rawurlencode($accountId).'/positions',
            query: ['refreshTerminalState' => $refreshTerminalState ? 'true' : 'false'],
            action: 'read_positions',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function readHistoryOrdersByTimeRange(string $accountId, DateTimeInterface $start, DateTimeInterface $end, int $limit = 100): array
    {
        return $this->getClient(
            path: '/users/current/accounts/'.rawurlencode($accountId).'/history-orders/time/'.$this->time($start).'/'.$this->time($end),
            query: ['limit' => max(1, min($limit, 1000))],
            action: 'read_history_orders',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function readDealsByTimeRange(string $accountId, DateTimeInterface $start, DateTimeInterface $end, int $limit = 100): array
    {
        return $this->getClient(
            path: '/users/current/accounts/'.rawurlencode($accountId).'/history-deals/time/'.$this->time($start).'/'.$this->time($end),
            query: ['limit' => max(1, min($limit, 1000))],
            action: 'read_history_deals',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postProvisioning(string $path, array $payload, string $action, ?string $transactionId = null): array
    {
        $request = $this->request();

        if ($transactionId !== null) {
            $request = $request->withHeaders(['transaction-id' => $transactionId]);
        }

        $response = $payload === []
            ? $request->post($this->url($this->provisioningBaseUrl(), $path))
            : $request->post($this->url($this->provisioningBaseUrl(), $path), $payload);

        return $this->result($response, $action);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function getProvisioning(string $path, array $query, string $action): array
    {
        $response = $this->request()->get($this->url($this->provisioningBaseUrl(), $path), $query);

        return $this->result($response, $action);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function getClient(string $path, array $query, string $action): array
    {
        $response = $this->request()->get($this->url($this->clientBaseUrl(), $path), $query);

        return $this->result($response, $action);
    }

    private function request(): PendingRequest
    {
        $token = $this->token();

        if (! (bool) config('services.metaapi.enabled', false)) {
            throw new RuntimeException('METAAPI_ENABLED must be true for live MetaApi validation.');
        }

        if (! filled($token)) {
            throw new RuntimeException('METAAPI_TOKEN is not configured.');
        }

        return Http::timeout($this->timeout())
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'auth-token' => (string) $token,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function result(Response $response, string $action): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            $payload = [
                'raw_body' => $response->body(),
            ];
        }

        return [
            'action' => $action,
            'ok' => $response->successful(),
            'status' => $response->status(),
            'payload' => $payload,
            'body' => $response->body(),
            'retry_after' => $this->retryAfter($response, $payload),
            'error' => $response->successful() ? null : $this->errorMessage($response, $payload),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function retryAfter(Response $response, array $payload): ?string
    {
        return $response->header('Retry-After')
            ?: data_get($payload, 'metadata.recommendedRetryTime')
            ?: data_get($payload, 'recommendedRetryTime');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function errorMessage(Response $response, array $payload): string
    {
        $message = data_get($payload, 'message')
            ?: data_get($payload, 'error')
            ?: $response->body();

        return trim((string) $message) !== '' ? (string) $message : 'MetaApi request failed.';
    }

    private function token(): ?string
    {
        return config('services.metaapi.token');
    }

    private function provisioningBaseUrl(): string
    {
        return (string) config('services.metaapi.provisioning_base_url', 'https://mt-provisioning-api-v1.agiliumtrade.agiliumtrade.ai');
    }

    private function clientBaseUrl(): string
    {
        return (string) config('services.metaapi.client_base_url', 'https://mt-client-api-v1.new-york.agiliumtrade.ai');
    }

    private function timeout(): int
    {
        return max(1, (int) config('services.metaapi.timeout', 30));
    }

    private function url(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }

    private function time(DateTimeInterface $time): string
    {
        return rawurlencode($time->format('Y-m-d\TH:i:s.v\Z'));
    }
}
