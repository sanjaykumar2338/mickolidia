<?php

namespace App\Services\Calendar;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class DemoEconomicCalendarService implements EconomicCalendarServiceInterface
{
    public function eventsForPeriod(CarbonImmutable $from, CarbonImmutable $to, string $displayTimezone = 'Europe/Berlin'): array
    {
        $rangeStart = $from->setTimezone($displayTimezone)->startOfDay();
        $rangeEnd = $to->setTimezone($displayTimezone)->endOfDay();
        $weekStart = $rangeStart->startOfWeek(CarbonInterface::MONDAY);

        return collect($this->demoBlueprints())
            ->map(function (array $event, int $index) use ($weekStart, $displayTimezone): array {
                [$hours, $minutes] = explode(':', $event['time']);

                return [
                    'id' => 'demo-'.$index,
                    'scheduled_at' => $weekStart
                        ->addDays($event['offset_days'])
                        ->setTime((int) $hours, (int) $minutes)
                        ->setTimezone($displayTimezone),
                    'currency' => $event['currency'],
                    'impact' => $event['impact'],
                    'event_name' => $event['event_name'],
                    'country' => $event['country'],
                    'forecast' => $event['forecast'],
                    'previous' => $event['previous'],
                ];
            })
            ->filter(fn (array $event): bool => $event['scheduled_at']->gte($rangeStart) && $event['scheduled_at']->lte($rangeEnd))
            ->values()
            ->all();
    }

    public function sourceLabel(): string
    {
        return 'Demo calendar feed';
    }

    public function isDemo(): bool
    {
        return true;
    }

    private function demoBlueprints(): array
    {
        return [
            [
                'offset_days' => 0,
                'time' => '08:00',
                'currency' => 'EUR',
                'impact' => 'medium',
                'country' => 'Germany',
                'event_name' => 'Germany Factory Orders MoM',
                'forecast' => '1.2%',
                'previous' => '-0.8%',
            ],
            [
                'offset_days' => 0,
                'time' => '16:00',
                'currency' => 'USD',
                'impact' => 'high',
                'country' => 'United States',
                'event_name' => 'ISM Services PMI',
                'forecast' => '53.1',
                'previous' => '52.6',
            ],
            [
                'offset_days' => 1,
                'time' => '10:00',
                'currency' => 'EUR',
                'impact' => 'high',
                'country' => 'Euro Area',
                'event_name' => 'CPI Flash Estimate YoY',
                'forecast' => '2.5%',
                'previous' => '2.6%',
            ],
            [
                'offset_days' => 1,
                'time' => '14:30',
                'currency' => 'USD',
                'impact' => 'high',
                'country' => 'United States',
                'event_name' => 'Core PCE Price Index MoM',
                'forecast' => '0.3%',
                'previous' => '0.2%',
            ],
            [
                'offset_days' => 2,
                'time' => '08:00',
                'currency' => 'GBP',
                'impact' => 'medium',
                'country' => 'United Kingdom',
                'event_name' => 'Nationwide House Prices YoY',
                'forecast' => '1.8%',
                'previous' => '1.5%',
            ],
            [
                'offset_days' => 2,
                'time' => '14:15',
                'currency' => 'USD',
                'impact' => 'high',
                'country' => 'United States',
                'event_name' => 'ADP Employment Change',
                'forecast' => '152K',
                'previous' => '140K',
            ],
            [
                'offset_days' => 3,
                'time' => '01:50',
                'currency' => 'JPY',
                'impact' => 'low',
                'country' => 'Japan',
                'event_name' => 'Foreign Bond Investment',
                'forecast' => '—',
                'previous' => '¥410.3B',
            ],
            [
                'offset_days' => 3,
                'time' => '13:00',
                'currency' => 'GBP',
                'impact' => 'medium',
                'country' => 'United Kingdom',
                'event_name' => 'BoE MPC Member Speech',
                'forecast' => '—',
                'previous' => '—',
            ],
            [
                'offset_days' => 3,
                'time' => '14:30',
                'currency' => 'USD',
                'impact' => 'high',
                'country' => 'United States',
                'event_name' => 'Initial Jobless Claims',
                'forecast' => '221K',
                'previous' => '218K',
            ],
            [
                'offset_days' => 4,
                'time' => '08:00',
                'currency' => 'EUR',
                'impact' => 'low',
                'country' => 'Germany',
                'event_name' => 'Industrial Production MoM',
                'forecast' => '0.4%',
                'previous' => '-0.3%',
            ],
            [
                'offset_days' => 4,
                'time' => '14:30',
                'currency' => 'USD',
                'impact' => 'high',
                'country' => 'United States',
                'event_name' => 'Non-Farm Payrolls',
                'forecast' => '188K',
                'previous' => '176K',
            ],
        ];
    }
}
