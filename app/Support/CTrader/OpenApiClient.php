<?php

namespace App\Support\CTrader;

use Illuminate\Support\Str;
use JsonException;
use Throwable;
use WebSocket\Client;
use WebSocket\Message\Close;
use WebSocket\Message\Message;
use WebSocket\Message\Text;
use WebSocket\Middleware\CloseHandler;
use WebSocket\Middleware\PingResponder;

class OpenApiClient
{
    private ?Client $client = null;

    public function __construct(
        private readonly string $uri,
        private readonly int|float $timeout = 15,
    ) {
    }

    public function connect(): void
    {
        if ($this->client instanceof Client && $this->client->isConnected()) {
            return;
        }

        $client = new Client($this->uri);
        $client->setTimeout($this->timeout);
        $client->addMiddleware(new PingResponder());
        $client->addMiddleware(new CloseHandler());
        $client->connect();

        $this->client = $client;
    }

    public function disconnect(): void
    {
        if (! $this->client instanceof Client) {
            return;
        }

        try {
            if ($this->client->isConnected()) {
                $this->client->send(new Close(1000, 'Client finished'));
            }
        } catch (Throwable) {
            // Ignore close acknowledgement problems on disconnect.
        } finally {
            $this->client->disconnect();
            $this->client = null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int>  $expectedPayloadTypes
     * @return array<string, mixed>
     */
    public function request(int $payloadType, array $payload, array $expectedPayloadTypes): array
    {
        $this->connect();

        if (! $this->client instanceof Client) {
            throw new CTraderException('cTrader WebSocket client could not be initialized.');
        }

        $clientMessageId = (string) Str::uuid();
        $body = [
            'clientMsgId' => $clientMessageId,
            'payloadType' => $payloadType,
            'payload' => $payload,
        ];

        try {
            $this->client->text(json_encode($body, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new CTraderException('Unable to encode cTrader request payload.', 0, $exception);
        }

        return $this->receivePayload($expectedPayloadTypes, $clientMessageId);
    }

    /**
     * @param  list<int>  $expectedPayloadTypes
     * @return array<string, mixed>
     */
    private function receivePayload(array $expectedPayloadTypes, string $clientMessageId): array
    {
        if (! $this->client instanceof Client) {
            throw new CTraderException('cTrader WebSocket client is not connected.');
        }

        while (true) {
            $message = $this->client->receive();

            if (! $message instanceof Message || ! $message instanceof Text) {
                continue;
            }

            try {
                $decoded = json_decode($message->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new CTraderException('Received invalid JSON from cTrader.', 0, $exception);
            }

            if (! is_array($decoded)) {
                continue;
            }

            $payloadType = (int) ($decoded['payloadType'] ?? 0);

            if ($payloadType === PayloadType::HEARTBEAT_EVENT) {
                continue;
            }

            if ($payloadType === PayloadType::ERROR_RES || $payloadType === PayloadType::OA_ERROR_RES) {
                $errorCode = (string) ($decoded['payload']['errorCode'] ?? $decoded['errorCode'] ?? 'UNKNOWN_ERROR');
                $description = (string) ($decoded['payload']['description'] ?? $decoded['description'] ?? 'cTrader returned an error response.');
                $message = trim($errorCode.' '.$description);

                if (str_contains($errorCode, 'TOKEN') || str_contains($errorCode, 'AUTH')) {
                    throw new CTraderTokenExpiredException($message);
                }

                throw new CTraderException($message);
            }

            if ($payloadType === PayloadType::ACCOUNTS_TOKEN_INVALIDATED_EVENT) {
                $reason = (string) ($decoded['payload']['reason'] ?? 'cTrader access token was invalidated.');
                throw new CTraderTokenExpiredException($reason);
            }

            if ($payloadType === PayloadType::CLIENT_DISCONNECT_EVENT) {
                $reason = (string) ($decoded['payload']['reason'] ?? 'cTrader disconnected the client session.');
                throw new CTraderException($reason);
            }

            if (in_array($payloadType, $expectedPayloadTypes, true) && ($decoded['clientMsgId'] ?? $clientMessageId) === $clientMessageId) {
                $payload = $decoded['payload'] ?? [];

                return is_array($payload) ? $payload : [];
            }

            if (in_array($payloadType, $expectedPayloadTypes, true) && ! array_key_exists('clientMsgId', $decoded)) {
                $payload = $decoded['payload'] ?? [];

                return is_array($payload) ? $payload : [];
            }
        }
    }
}
