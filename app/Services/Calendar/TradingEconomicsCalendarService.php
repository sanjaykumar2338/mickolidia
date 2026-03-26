<?php

namespace App\Services\Calendar;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TradingEconomicsCalendarService implements EconomicCalendarServiceInterface
{
    public function eventsForPeriod(CarbonImmutable $from, CarbonImmutable $to, string $displayTimezone = 'Europe/Berlin'): array
    {
        $apiKey = (string) config('services.trading_economics.api_key', '');

        if ($apiKey === '') {
            return [];
        }

        try {
            // Trading Economics snapshot endpoint supports date-range calendar queries with c={api_key}.
            $response = Http::baseUrl((string) config('services.trading_economics.base_url', 'https://api.tradingeconomics.com'))
                ->acceptJson()
                ->timeout(10)
                ->get(sprintf('/calendar/country/All/%s/%s', $from->toDateString(), $to->toDateString()), [
                    'c' => $apiKey,
                    'f' => 'json',
                ]);

            if (! $response->successful()) {
                Log::warning('Trading Economics calendar request failed.', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                return [];
            }

            return collect($payload)
                ->map(fn (array $event, int $index): ?array => $this->normalizeEvent($event, $index, $displayTimezone))
                ->filter()
                ->sortBy('scheduled_at')
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            Log::warning('Trading Economics calendar request threw an exception.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    public function sourceLabel(): string
    {
        return 'Trading Economics API';
    }

    public function isDemo(): bool
    {
        return false;
    }

    private function normalizeEvent(array $event, int $index, string $displayTimezone): ?array
    {
        $date = $event['Date'] ?? null;
        $eventName = trim((string) ($event['Event'] ?? $event['Category'] ?? ''));

        if (! is_string($date) || $date === '' || $eventName === '') {
            return null;
        }

        $scheduledAt = CarbonImmutable::parse($date, config('app.timezone', 'UTC'))
            ->setTimezone($displayTimezone);

        return [
            'id' => (string) ($event['CalendarId'] ?? 'te-'.$index),
            'scheduled_at' => $scheduledAt,
            'currency' => strtoupper(trim((string) ($event['Currency'] ?? ''))) ?: '—',
            'impact' => $this->mapImpact((int) ($event['Importance'] ?? 1)),
            'event_name' => $eventName,
            'country' => trim((string) ($event['Country'] ?? '')),
            'forecast' => $this->normalizeValue($event['Forecast'] ?? null),
            'previous' => $this->normalizeValue($event['Previous'] ?? null),
        ];
    }

    private function mapImpact(int $importance): string
    {
        return match (true) {
            $importance >= 3 => 'high',
            $importance === 2 => 'medium',
            default => 'low',
        };
    }

    private function normalizeValue(mixed $value): string
    {
        $string = trim((string) $value);

        return $string !== '' ? $string : '—';
    }
}
