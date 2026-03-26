@extends('layouts.public')

@php
    $impactStyles = [
        'high' => 'border border-rose-400/24 bg-rose-500/12 text-rose-200',
        'medium' => 'border border-amber-300/24 bg-amber-400/12 text-amber-100',
        'low' => 'border border-emerald-400/24 bg-emerald-500/12 text-emerald-200',
    ];
@endphp

@section('title', __('site.news.title').' | '.__('site.meta.brand'))

@section('content')
    <section class="px-6 pt-12 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <span class="section-label">{{ __('site.news.eyebrow') }}</span>

            <div class="mt-5 flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <h1 class="text-4xl font-semibold text-white sm:text-5xl">{{ __('site.news.title') }}</h1>
                    <p class="mt-4 text-base leading-8 text-slate-300">{{ __('site.news.description') }}</p>
                </div>

                <div class="gold-pill rounded-full px-4 py-2 text-sm font-medium">
                    {{ __('site.news.timezone_badge', ['timezone' => $displayTimezone, 'abbr' => $timezoneAbbreviation]) }}
                </div>
            </div>

            <div class="mt-8 grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
                <article class="surface-panel rounded-[2rem] p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-200">{{ __('site.news.warning_title') }}</p>
                    <p class="mt-4 text-sm leading-7 text-slate-300">{{ __('site.news.warning_copy') }}</p>
                </article>

                <article class="surface-panel rounded-[2rem] p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.26em] text-slate-400">{{ __('site.news.data_source_label') }}</p>
                    <p class="mt-3 text-xs font-semibold uppercase tracking-[0.24em] {{ $calendarIsDemoMode ? 'text-amber-200' : 'text-emerald-200' }}">
                        {{ $calendarIsDemoMode ? __('site.news.mode_demo') : __('site.news.mode_live') }}
                    </p>
                    <p class="mt-3 text-lg font-semibold text-white">{{ $calendarSourceLabel }}</p>
                    <p class="mt-3 text-sm leading-7 {{ $calendarIsDemoMode ? 'text-amber-100/90' : 'text-slate-300' }}">
                        {{ $calendarIsDemoMode ? __('site.news.demo_notice') : __('site.news.live_notice') }}
                    </p>
                </article>
            </div>

            <form method="GET" class="mt-8 surface-panel rounded-[2rem] p-4 sm:p-6">
                <div class="grid gap-4 xl:grid-cols-[repeat(3,minmax(0,1fr))_auto] xl:items-end">
                    <label class="block">
                        <span class="mb-3 block text-sm font-medium text-slate-300">{{ __('site.news.filters.impact') }}</span>
                        <select
                            name="impact"
                            class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3.5 text-white outline-none transition focus:border-amber-400/35"
                        >
                            <option value="all" @selected($filters['impact'] === 'all')>{{ __('site.news.filters.all_impacts') }}</option>
                            <option value="high" @selected($filters['impact'] === 'high')>{{ __('site.news.impact.high') }}</option>
                            <option value="medium" @selected($filters['impact'] === 'medium')>{{ __('site.news.impact.medium') }}</option>
                            <option value="low" @selected($filters['impact'] === 'low')>{{ __('site.news.impact.low') }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-3 block text-sm font-medium text-slate-300">{{ __('site.news.filters.currency') }}</span>
                        <select
                            name="currency"
                            class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3.5 text-white outline-none transition focus:border-amber-400/35"
                        >
                            <option value="all" @selected($filters['currency'] === 'all')>{{ __('site.news.filters.all_currencies') }}</option>
                            @foreach ($availableCurrencies as $currency)
                                <option value="{{ $currency }}" @selected($filters['currency'] === $currency)>{{ $currency }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-3 block text-sm font-medium text-slate-300">{{ __('site.news.filters.range') }}</span>
                        <select
                            name="range"
                            class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3.5 text-white outline-none transition focus:border-amber-400/35"
                        >
                            @foreach ($rangeOptions as $rangeOption)
                                <option value="{{ $rangeOption }}" @selected($filters['range'] === $rangeOption)>{{ __('site.news.filters.range_options.'.$rangeOption) }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="flex flex-col gap-3 sm:flex-row xl:flex-col">
                        <button type="submit" class="primary-cta rounded-full px-6 py-3.5 text-sm font-semibold">
                            {{ __('site.news.filters.apply') }}
                        </button>
                        <a href="{{ route('news') }}" class="inline-flex items-center justify-center rounded-full border border-white/10 px-6 py-3.5 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                            {{ __('site.news.filters.reset') }}
                        </a>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <label class="inline-flex items-center gap-3 rounded-full border border-white/10 bg-white/4 px-4 py-3 text-sm text-slate-200">
                        <input
                            type="checkbox"
                            name="high_only"
                            value="1"
                            class="h-4 w-4 rounded border-white/20 bg-slate-950 text-amber-400 focus:ring-amber-300/40"
                            @checked($filters['high_only'])
                        >
                        <span>{{ __('site.news.filters.high_only') }}</span>
                    </label>

                    <p class="text-sm text-slate-400">
                        {{ __('site.news.range_caption', [
                            'from' => $rangeStart->locale(app()->getLocale())->isoFormat('D MMM'),
                            'to' => $rangeEnd->locale(app()->getLocale())->isoFormat('D MMM'),
                        ]) }}
                    </p>
                </div>
            </form>

            <div class="mt-8 overflow-hidden rounded-[2rem] border border-white/10 bg-slate-950/90 shadow-[0_28px_80px_rgba(2,6,23,0.42)]">
                <div class="search-scroll max-h-[70vh] overflow-auto">
                    <table class="min-w-[58rem] w-full border-separate border-spacing-0">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                                <th class="sticky top-0 z-10 border-b border-white/10 bg-slate-950/95 px-5 py-4 backdrop-blur-xl">{{ __('site.news.table.time') }}</th>
                                <th class="sticky top-0 z-10 border-b border-white/10 bg-slate-950/95 px-5 py-4 backdrop-blur-xl">{{ __('site.news.table.currency') }}</th>
                                <th class="sticky top-0 z-10 border-b border-white/10 bg-slate-950/95 px-5 py-4 backdrop-blur-xl">{{ __('site.news.table.impact') }}</th>
                                <th class="sticky top-0 z-10 border-b border-white/10 bg-slate-950/95 px-5 py-4 backdrop-blur-xl">{{ __('site.news.table.event') }}</th>
                                <th class="sticky top-0 z-10 border-b border-white/10 bg-slate-950/95 px-5 py-4 backdrop-blur-xl">{{ __('site.news.table.forecast') }}</th>
                                <th class="sticky top-0 z-10 border-b border-white/10 bg-slate-950/95 px-5 py-4 backdrop-blur-xl">{{ __('site.news.table.previous') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($events as $event)
                                <tr class="transition hover:bg-white/[0.03]">
                                    <td class="border-b border-white/6 px-5 py-4 align-top">
                                        <p class="font-semibold text-white">{{ $event['display_time'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $event['display_date'] }}</p>
                                    </td>
                                    <td class="border-b border-white/6 px-5 py-4 align-top">
                                        <span class="inline-flex rounded-full border border-white/10 bg-white/4 px-3 py-1 text-sm font-semibold text-slate-100">{{ $event['currency'] }}</span>
                                    </td>
                                    <td class="border-b border-white/6 px-5 py-4 align-top">
                                        <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $impactStyles[$event['impact']] ?? $impactStyles['low'] }}">
                                            {{ __('site.news.impact.'.$event['impact']) }}
                                        </span>
                                    </td>
                                    <td class="border-b border-white/6 px-5 py-4 align-top">
                                        <p class="font-medium text-white">{{ $event['event_name'] }}</p>
                                        @if (! empty($event['country']))
                                            <p class="mt-1 text-xs text-slate-500">{{ $event['country'] }}</p>
                                        @endif
                                    </td>
                                    <td class="border-b border-white/6 px-5 py-4 align-top text-sm text-slate-200">{{ $event['forecast'] }}</td>
                                    <td class="border-b border-white/6 px-5 py-4 align-top text-sm text-slate-200">{{ $event['previous'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-400">
                                        {{ __('site.news.table.empty') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection
