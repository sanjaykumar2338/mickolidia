<?php

namespace App\Services\MetaApi;

use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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
            timeout: $this->historyTimeout(),
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
            timeout: $this->historyTimeout(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postProvisioning(string $path, array $payload, string $action, ?string $transactionId = null): array
    {
        $request = $this->request();
        $url = $this->url($this->provisioningBaseUrl(), $path);
        $headers = $this->requestHeaders($transactionId);
        $requestContext = $this->requestContext('POST', $url, $headers, $payload, [], $transactionId);

        if ($transactionId !== null) {
            $request = $request->withHeaders(['transaction-id' => $transactionId]);
        }

        try {
            $response = $payload === []
                ? $request->post($url)
                : $request->post($url, $payload);

            return $this->result($response, $action, $requestContext);
        } catch (Throwable $exception) {
            return $this->exceptionResult($exception, $action, $requestContext);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function getProvisioning(string $path, array $query, string $action): array
    {
        $url = $this->url($this->provisioningBaseUrl(), $path);
        $requestContext = $this->requestContext('GET', $url, $this->requestHeaders(), [], $query);

        try {
            $response = $this->request()->get($url, $query);

            return $this->result($response, $action, $requestContext);
        } catch (Throwable $exception) {
            return $this->exceptionResult($exception, $action, $requestContext);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function getClient(string $path, array $query, string $action, ?int $timeout = null): array
    {
        $url = $this->url($this->clientBaseUrl(), $path);
        $requestContext = $this->requestContext('GET', $url, $this->requestHeaders(), [], $query);

        try {
            $response = $this->request($timeout)->get($url, $query);

            return $this->result($response, $action, $requestContext);
        } catch (Throwable $exception) {
            return $this->exceptionResult($exception, $action, $requestContext);
        }
    }

    private function request(?int $timeout = null): PendingRequest
    {
        $token = $this->token();

        if (! (bool) config('services.metaapi.enabled', false)) {
            throw new RuntimeException('METAAPI_ENABLED must be true for live MetaApi validation.');
        }

        if (! filled($token)) {
            throw new RuntimeException('METAAPI_TOKEN is not configured.');
        }

        return Http::timeout($timeout ?? $this->timeout())
            ->connectTimeout($this->connectTimeout())
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'auth-token' => (string) $token,
            ]);
    }

    /**
     * @param  array<string, mixed>  $requestContext
     * @return array<string, mixed>
     */
    private function result(Response $response, string $action, array $requestContext): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            $payload = [
                'raw_body' => $response->body(),
            ];
        }

        $errorMessage = $response->successful() ? null : $this->errorMessage($response, $payload);
        $traceId = $this->traceId($payload);
        $requestId = $this->requestId($response, $payload) ?: $traceId;

        return [
            'action' => $action,
            'ok' => $response->successful(),
            'status' => $response->status(),
            'request' => $requestContext,
            'request_id' => $requestId,
            'trace_id' => $traceId,
            'transaction_id' => $this->transactionIdFromResponse($response, $payload, $requestContext),
            'error_code' => $response->successful() ? null : $this->errorCode($payload),
            'error_message' => $errorMessage,
            'details' => data_get($payload, 'details'),
            'payload' => $payload,
            'body' => $response->body(),
            'retry_after' => $this->retryAfter($response, $payload),
            'error' => $errorMessage,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $requestContext
     * @return array<string, mixed>
     */
    private function exceptionResult(Throwable $exception, string $action, ?array $requestContext = null): array
    {
        return [
            'action' => $action,
            'ok' => false,
            'status' => 0,
            'request' => $requestContext,
            'request_id' => null,
            'trace_id' => null,
            'transaction_id' => data_get($requestContext, 'transaction_id'),
            'error_code' => $exception::class,
            'error_message' => $exception->getMessage(),
            'details' => null,
            'payload' => [
                'exception' => $exception::class,
                'message' => $exception instanceof ConnectionException
                    ? 'MetaApi request connection/timeout failure.'
                    : 'MetaApi request failed before an HTTP response was received.',
            ],
            'body' => '',
            'retry_after' => null,
            'error' => $exception->getMessage(),
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function errorCode(array $payload): ?string
    {
        $code = data_get($payload, 'error')
            ?: data_get($payload, 'errorCode')
            ?: data_get($payload, 'code')
            ?: data_get($payload, 'name');

        return filled($code) ? (string) $code : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestId(Response $response, array $payload): ?string
    {
        $requestId = $response->header('request-id')
            ?: $response->header('x-request-id')
            ?: $response->header('x-amzn-requestid')
            ?: data_get($payload, 'requestId')
            ?: data_get($payload, 'requestID')
            ?: data_get($payload, 'metadata.requestId');

        return filled($requestId) ? (string) $requestId : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function traceId(array $payload): ?string
    {
        $traceId = data_get($payload, 'traceId')
            ?: data_get($payload, 'trace_id')
            ?: data_get($payload, 'metadata.traceId')
            ?: data_get($payload, 'metadata.trace_id');

        return filled($traceId) ? (string) $traceId : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $requestContext
     */
    private function transactionIdFromResponse(Response $response, array $payload, array $requestContext): ?string
    {
        $transactionId = $response->header('transaction-id')
            ?: $response->header('x-transaction-id')
            ?: data_get($payload, 'transactionId')
            ?: data_get($payload, 'metadata.transactionId')
            ?: data_get($requestContext, 'transaction_id');

        return filled($transactionId) ? (string) $transactionId : null;
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(?string $transactionId = null): array
    {
        return array_filter([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'auth-token' => (string) $this->token(),
            'transaction-id' => $transactionId,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function requestContext(string $method, string $url, array $headers, array $payload = [], array $query = [], ?string $transactionId = null): array
    {
        $fullUrl = $this->urlWithQuery($url, $query);
        $sanitizedHeaders = $this->sanitizeForDiagnostics($headers);
        $sanitizedPayload = $this->sanitizeForDiagnostics($payload);
        $sanitizedQuery = $this->sanitizeForDiagnostics($query);

        return [
            'method' => Str::upper($method),
            'url' => $fullUrl,
            'headers' => $sanitizedHeaders,
            'query' => $query === [] ? null : $sanitizedQuery,
            'payload' => $payload === [] ? null : $sanitizedPayload,
            'transaction_id' => $transactionId,
            'curl' => $this->curlCommand(Str::upper($method), $fullUrl, $sanitizedHeaders, $payload === [] ? null : $sanitizedPayload),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function urlWithQuery(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        return $url.(Str::contains($url, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|null  $payload
     */
    private function curlCommand(string $method, string $url, array $headers, ?array $payload): string
    {
        $parts = [
            'curl',
            '--request',
            $this->shellQuote($method),
            $this->shellQuote($url),
        ];

        foreach ($headers as $key => $value) {
            $parts[] = '--header';
            $parts[] = $this->shellQuote($key.': '.$value);
        }

        if ($payload !== null) {
            $parts[] = '--data-raw';
            $parts[] = $this->shellQuote(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
        }

        return implode(' ', $parts);
    }

    private function shellQuote(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }

    private function sanitizeForDiagnostics(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                if (is_string($key) && preg_match('/token|password|secret|authorization|auth/i', $key)) {
                    $sanitized[$key] = '[redacted]';

                    continue;
                }

                $sanitized[$key] = $this->sanitizeForDiagnostics($item);
            }

            return $sanitized;
        }

        if (! is_string($value)) {
            return $value;
        }

        $token = (string) $this->token();

        return $token !== '' ? str_replace($token, '[redacted]', $value) : $value;
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

    private function connectTimeout(): int
    {
        return max(1, (int) config('services.metaapi.connect_timeout', 10));
    }

    private function historyTimeout(): int
    {
        return max(1, (int) config('services.metaapi.history.timeout', $this->timeout()));
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
