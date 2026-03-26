<?php

namespace App\Services\Calendar;

use Carbon\CarbonImmutable;

interface EconomicCalendarServiceInterface
{
    public function eventsForPeriod(CarbonImmutable $from, CarbonImmutable $to, string $displayTimezone = 'Europe/Berlin'): array;

    public function sourceLabel(): string;

    public function isDemo(): bool;
}
