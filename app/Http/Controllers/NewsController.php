<?php

namespace App\Http\Controllers;

use App\Services\Calendar\EconomicCalendarServiceInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(Request $request, EconomicCalendarServiceInterface $calendarService): View
    {
        $validated = $request->validate([
            'impact' => ['nullable', 'in:all,high,medium,low'],
            'currency' => ['nullable', 'alpha', 'max:8'],
            'range' => ['nullable', 'in:today,this_week,next_week'],
            'high_only' => ['nullable', 'boolean'],
        ]);

        $displayTimezone = (string) config('wolforix.economic_calendar.display_timezone', 'Europe/Berlin');
        $nowInTimezone = CarbonImmutable::now($displayTimezone);
        $range = $validated['range'] ?? (string) config('wolforix.economic_calendar.default_range', 'this_week');
        $impact = $validated['impact'] ?? 'all';
        $currency = $this->normalizeCurrencyFilter($validated['currency'] ?? 'all');
        $highOnly = $request->boolean('high_only');

        if ($highOnly) {
            $impact = 'high';
        }

        [$rangeStart, $rangeEnd] = $this->resolveRange($range, $nowInTimezone);

        $allEvents = collect($calendarService->eventsForPeriod($rangeStart, $rangeEnd, $displayTimezone))
            ->map(function (array $event) use ($displayTimezone): array {
                $scheduledAt = $event['scheduled_at'] instanceof CarbonImmutable
                    ? $event['scheduled_at']
                    : CarbonImmutable::parse((string) $event['scheduled_at'], $displayTimezone);

                $localized = $scheduledAt->setTimezone($displayTimezone)->locale(app()->getLocale());

                return array_merge($event, [
                    'scheduled_at' => $localized,
                    'display_time' => $localized->format('H:i'),
                    'display_date' => $localized->isoFormat('ddd, D MMM'),
                ]);
            })
            ->sortBy('scheduled_at')
            ->values();

        $events = $allEvents
            ->when($impact !== 'all', fn ($collection) => $collection->where('impact', $impact))
            ->when($currency !== 'all', fn ($collection) => $collection->where('currency', $currency))
            ->values();

        $availableCurrencies = $allEvents
            ->pluck('currency')
            ->filter(fn (?string $value) => filled($value) && $value !== '—')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return view('public.news', [
            'events' => $events,
            'filters' => [
                'impact' => $impact,
                'currency' => $currency,
                'range' => $range,
                'high_only' => $highOnly,
            ],
            'availableCurrencies' => $availableCurrencies,
            'rangeOptions' => ['today', 'this_week', 'next_week'],
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'displayTimezone' => $displayTimezone,
            'timezoneAbbreviation' => $rangeStart->format('T'),
            'calendarSourceLabel' => $calendarService->sourceLabel(),
            'calendarIsDemoMode' => $calendarService->isDemo(),
        ]);
    }

    private function normalizeCurrencyFilter(string $currency): string
    {
        return strtolower($currency) === 'all' ? 'all' : strtoupper($currency);
    }

    private function resolveRange(string $range, CarbonImmutable $anchor): array
    {
        return match ($range) {
            'today' => [
                $anchor->startOfDay(),
                $anchor->endOfDay(),
            ],
            'next_week' => [
                $anchor->startOfWeek(CarbonInterface::MONDAY)->addWeek()->startOfDay(),
                $anchor->startOfWeek(CarbonInterface::MONDAY)->addWeek()->endOfWeek(CarbonInterface::SUNDAY)->endOfDay(),
            ],
            default => [
                $anchor->startOfWeek(CarbonInterface::MONDAY)->startOfDay(),
                $anchor->endOfWeek(CarbonInterface::SUNDAY)->endOfDay(),
            ],
        };
    }
}
