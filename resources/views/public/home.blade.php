@extends('layouts.public')

@section('title', __('site.meta.default_title'))

@php
    $initialPlan = $defaultChallengeType !== null && $defaultChallengeSize !== null
        ? $challengeCatalog[$defaultChallengeType]['plans'][(int) $defaultChallengeSize]
        : null;
    $defaultCheckoutUrl = $defaultChallengeType !== null && $defaultChallengeSize !== null
        ? route('checkout.show', [
            'challenge_type' => $defaultChallengeType,
            'account_size' => $defaultChallengeSize,
            'currency' => $defaultCurrency,
        ])
        : route('checkout.show');
    $formatMoney = static function (int|float $amount, string $currency = 'USD'): string {
        return match ($currency) {
            'USD' => '$'.number_format($amount, 0),
            'EUR' => '€'.number_format($amount, 0),
            'GBP' => '£'.number_format($amount, 0),
            default => $currency.' '.number_format($amount, 0),
        };
    };
    $initialPrice = $initialPlan !== null ? $formatMoney($initialPlan['discounted_price'], $defaultCurrency) : '';
    $initialListPrice = $initialPlan !== null ? $formatMoney($initialPlan['list_price'], $defaultCurrency) : '';
    $featureIcons = [
        <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 15.75 9 10.5l3.75 3.75 7.5-8.25" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 20.25h16.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6.75h3.75V10.5" />
        </svg>
        SVG,
        <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.5h19.5v9a2.25 2.25 0 0 1-2.25 2.25H4.5A2.25 2.25 0 0 1 2.25 16.5v-9Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 9.75h19.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 14.25h3" />
        </svg>
        SVG,
        <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 6h11.25A2.25 2.25 0 0 1 21 8.25v7.5A2.25 2.25 0 0 1 18.75 18H7.5A2.25 2.25 0 0 1 5.25 15.75V8.25A2.25 2.25 0 0 1 7.5 6Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 9H3.75A1.5 1.5 0 0 0 2.25 10.5v3A1.5 1.5 0 0 0 3.75 15h1.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 12h.008v.008h-.008V12Zm-3 0h.008v.008h-.008V12Z" />
        </svg>
        SVG,
        <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4.5 2.25" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-9-9" />
        </svg>
        SVG,
    ];
    $challengeUi = [
        'unlimited' => __('site.home.challenge_selector.unlimited'),
        'discount_badge' => __('site.home.challenge_selector.discount_badge'),
        'discount_urgency' => __('site.home.challenge_selector.discount_urgency'),
        'phase_titles' => trans('site.home.challenge_selector.phase_titles'),
        'metrics' => trans('site.home.challenge_selector.metrics'),
        'value_templates' => trans('site.home.challenge_selector.value_templates'),
        'consistency_required' => __('site.home.challenge_selector.consistency_required'),
    ];
@endphp

@section('content')
    <section class="px-6 pt-10 lg:px-8 lg:pt-14">
        <div class="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-[1.05fr_minmax(0,0.95fr)]">
            <div>
                <span class="section-label">{{ __('site.home.eyebrow') }}</span>
                <h1 class="mt-6 max-w-4xl text-4xl font-semibold leading-tight text-white sm:text-5xl lg:text-6xl">
                    {{ __('site.home.title') }}
                </h1>
                <p class="mt-6 max-w-3xl text-base leading-8 text-slate-300 sm:text-lg">
                    {{ __('site.home.description') }}
                </p>

                <div class="mt-6 flex flex-wrap gap-3">
                    @foreach (trans('site.home.badges') as $badge)
                        <span class="gold-pill rounded-full px-4 py-2 text-sm font-medium">{{ $badge }}</span>
                    @endforeach
                </div>

                <div class="mt-8 flex flex-wrap items-start gap-4">
                    <a
                        href="{{ $defaultCheckoutUrl }}"
                        data-checkout-cta
                        data-checkout-base="{{ route('checkout.show') }}"
                        class="primary-cta rounded-full px-8 py-4 text-base font-semibold"
                    >
                        {{ __('site.home.primary_cta') }}
                    </a>
                    <div class="flex flex-col items-start">
                        <a href="{{ route('trial.register') }}" class="ghost-cta rounded-full px-8 py-4 text-base font-semibold">
                            {{ __('site.home.free_trial_cta') }}
                        </a>
                        <p class="mt-3 max-w-xs text-sm leading-6 text-slate-400">{{ __('site.home.free_trial_caption') }}</p>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="{{ route('dashboard') }}" class="inline-flex rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:border-white/25 hover:bg-white/6">
                        {{ __('site.home.secondary_cta') }}
                    </a>
                </div>

                <div class="mt-10 surface-panel rounded-[2rem] p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-amber-300">{{ __('site.home.hero_panel.title') }}</p>
                            <p class="mt-2 text-sm leading-7 text-slate-400">{{ __('site.home.hero_panel.caption') }}</p>
                        </div>
                        <div class="rounded-full border border-sky-400/20 bg-sky-500/10 px-4 py-2 text-sm text-sky-100">
                            {{ __('site.home.hero_panel.status') }}
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        @foreach (trans('site.home.hero_panel.items') as $item)
                            <div class="surface-card rounded-2xl px-4 py-4 text-sm text-slate-200">{{ $item }}</div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="relative">
                <div class="surface-panel overflow-hidden rounded-[2rem] border border-white/8 p-4">
                    <div class="overflow-hidden rounded-[1.6rem] border border-white/6 bg-black/40">
                        <img src="{{ asset('branding/659E27F6-E56C-4991-8383-F0E0B8FA9FBB.png') }}" alt="Wolforix branding reference" class="h-full w-full object-cover">
                    </div>
                </div>

                <div class="absolute -bottom-5 left-5 right-5 surface-card rounded-[1.8rem] p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.home.image_caption') }}</p>
                    <p class="mt-3 text-sm leading-7 text-slate-300">{{ __('site.home.image_copy') }}</p>
                </div>
            </div>
        </div>

        <div class="mx-auto mt-14 grid max-w-7xl gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach (trans('site.home.feature_cards') as $card)
                <article class="surface-panel rounded-[2rem] p-6">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-amber-400/20 bg-amber-400/10 text-amber-200 shadow-[0_18px_40px_rgba(244,183,74,0.12)]">
                        {!! $featureIcons[$loop->index] ?? $featureIcons[0] !!}
                    </span>
                    <p class="mt-5 max-w-xs text-xl font-semibold leading-8 text-white">{{ $card }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section id="plans" class="px-6 pt-20 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <span class="section-label">{{ __('site.home.plans.eyebrow') }}</span>
            <div class="mt-5 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <h2 class="text-3xl font-semibold text-white sm:text-4xl">{{ __('site.home.plans.title') }}</h2>
                    <p class="mt-4 text-base leading-8 text-slate-300">{{ __('site.home.plans.description') }}</p>
                </div>
                <div class="gold-pill rounded-full px-4 py-2 text-sm font-medium">
                    {{ __('site.home.plans.badge') }}
                </div>
            </div>

            @if ($initialPlan !== null)
                <div
                    data-challenge-selector
                    data-default-currency="{{ $defaultCurrency }}"
                    data-default-type="{{ $defaultChallengeType }}"
                    data-default-size="{{ $defaultChallengeSize }}"
                    data-unlimited-label="{{ __('site.home.challenge_selector.unlimited') }}"
                    data-days-label="{{ __('site.home.days') }}"
                    class="mt-10"
                >
                    <script type="application/json" data-challenge-catalog>@json($challengeCatalog)</script>
                    <script type="application/json" data-challenge-currencies>@json($currencies)</script>
                    <script type="application/json" data-challenge-ui>@json($challengeUi)</script>

                    <div class="grid gap-6 xl:grid-cols-[0.92fr_1.08fr]">
                        <div class="surface-panel rounded-[2rem] p-6">
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('site.home.challenge_selector.currency_label') }}</p>
                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                @foreach ($currencies as $currencyCode => $currencyMeta)
                                    <button
                                        type="button"
                                        data-challenge-currency="{{ $currencyCode }}"
                                        class="challenge-currency-button rounded-[1.5rem] border border-white/8 bg-white/3 px-4 py-4 text-left text-slate-300 transition hover:border-amber-300/20 hover:bg-white/6"
                                    >
                                        <span class="block text-sm font-semibold tracking-[0.22em] text-white">{{ $currencyCode }}</span>
                                        <span class="mt-2 block text-xs uppercase tracking-[0.22em] text-slate-400">{{ __('site.home.challenge_selector.currencies.'.$currencyCode) }}</span>
                                    </button>
                                @endforeach
                            </div>

                            <div class="mt-8">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('site.home.challenge_selector.type_label') }}</p>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    @foreach ($challengeCatalog as $challengeTypeKey => $challengeType)
                                        <button
                                            type="button"
                                            data-challenge-type="{{ $challengeTypeKey }}"
                                            data-label="{{ __('site.home.challenge_selector.types.'.$challengeTypeKey.'.label') }}"
                                            data-description="{{ __('site.home.challenge_selector.types.'.$challengeTypeKey.'.description') }}"
                                            data-note-title="{{ __('site.home.challenge_selector.types.'.$challengeTypeKey.'.note_title') }}"
                                            data-note-body="{{ __('site.home.challenge_selector.types.'.$challengeTypeKey.'.note_body') }}"
                                            class="challenge-type-button rounded-[1.8rem] border border-white/8 bg-white/3 p-5 text-left text-slate-300 transition hover:border-amber-300/20 hover:bg-white/6"
                                        >
                                            <span class="block text-lg font-semibold text-white">{{ __('site.home.challenge_selector.types.'.$challengeTypeKey.'.label') }}</span>
                                            <span class="mt-2 block text-sm leading-7 text-slate-400">{{ __('site.home.challenge_selector.types.'.$challengeTypeKey.'.description') }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mt-8">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('site.home.challenge_selector.size_label') }}</p>
                                <div class="mt-4 flex flex-wrap gap-3">
                                    @foreach ($challengeSizes as $size)
                                        <button
                                            type="button"
                                            data-challenge-size="{{ $size }}"
                                            class="challenge-size-button rounded-full border border-white/8 bg-white/3 px-5 py-3 text-sm font-semibold text-slate-200 transition hover:border-amber-300/20 hover:bg-white/6"
                                        >
                                            {{ (int) ($size / 1000) }}K
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mt-8 rounded-[1.8rem] border border-white/8 bg-white/3 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-300">{{ __('site.home.challenge_selector.insight_title') }}</p>
                                <p data-challenge-description-text class="mt-3 text-sm leading-7 text-slate-300">
                                    {{ __('site.home.challenge_selector.types.'.$defaultChallengeType.'.description') }}
                                </p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @foreach (trans('site.home.challenge_selector.highlights') as $highlight)
                                        <span class="gold-pill rounded-full px-4 py-2 text-xs font-semibold">{{ $highlight }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="surface-panel rounded-[2rem] p-6">
                            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <p data-challenge-badge class="text-sm font-semibold tracking-[0.24em] text-amber-300">{{ __('site.home.challenge_selector.types.'.$defaultChallengeType.'.label') }}</p>
                                    <h3 data-plan-title class="mt-2 text-3xl font-semibold text-white sm:text-4xl">
                                        {{ __('site.home.challenge_selector.types.'.$defaultChallengeType.'.label') }} / {{ (int) ($initialPlan['account_size'] / 1000) }}K
                                    </h3>
                                    <div class="mt-5 flex flex-wrap items-center gap-3">
                                        <span data-plan-discount-badge class="{{ $initialPlan['discount']['enabled'] ? '' : 'hidden ' }}gold-pill rounded-full px-4 py-2 text-xs font-semibold">
                                            {{ __('site.home.challenge_selector.discount_badge') }}
                                        </span>
                                        <span data-plan-currency-code class="rounded-full border border-white/8 bg-white/3 px-4 py-2 text-xs font-semibold tracking-[0.24em] text-slate-200">
                                            {{ $defaultCurrency }}
                                        </span>
                                        <span class="text-xs font-semibold uppercase tracking-[0.26em] text-slate-400">{{ __('site.home.challenge_selector.current_price') }}</span>
                                    </div>
                                    <p data-plan-price class="mt-4 text-4xl font-semibold text-white">{{ $initialPrice }}</p>
                                    <div class="mt-4 flex flex-wrap items-center gap-3">
                                        <span data-plan-original-wrap class="{{ $initialPlan['discount']['enabled'] ? '' : 'hidden ' }}rounded-full border border-white/8 bg-white/3 px-4 py-2 text-sm text-slate-400">
                                            {{ __('site.home.challenge_selector.original_price') }}
                                            <span data-plan-original-price class="ml-2 font-semibold line-through">{{ $initialListPrice }}</span>
                                        </span>
                                        <span data-plan-discount-urgency class="{{ $initialPlan['discount']['enabled'] ? '' : 'hidden ' }}text-sm font-medium text-amber-100">
                                            {{ __('site.home.challenge_selector.discount_urgency') }}
                                        </span>
                                    </div>
                                </div>

                                <a
                                    href="{{ $defaultCheckoutUrl }}"
                                    data-checkout-cta
                                    data-checkout-base="{{ route('checkout.show') }}"
                                    class="primary-cta rounded-full px-8 py-4 text-base font-semibold"
                                >
                                    {{ __('site.home.challenge_selector.start_button') }}
                                </a>
                            </div>

                            <div data-plan-detail-groups class="mt-8 grid gap-4 xl:grid-cols-3">
                                @foreach ($initialPlan['phases'] as $phase)
                                    <section class="surface-card rounded-[1.6rem] p-5">
                                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">{{ __('site.home.challenge_selector.phase_titles.'.$phase['key']) }}</p>
                                        <dl class="mt-4 space-y-3 text-sm">
                                            <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                                <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.profit_target') }}</dt>
                                                <dd class="font-semibold text-white">{{ $phase['profit_target'] }}%</dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                                <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.daily_loss') }}</dt>
                                                <dd class="font-semibold text-white">{{ $phase['daily_loss_limit'] }}%</dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                                <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.total_loss') }}</dt>
                                                <dd class="font-semibold text-white">{{ $phase['max_loss_limit'] }}%</dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                                <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.minimum_days') }}</dt>
                                                <dd class="font-semibold text-white">{{ $phase['minimum_trading_days'] }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                                <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.max_trading_days') }}</dt>
                                                <dd class="font-semibold text-white">{{ $phase['maximum_trading_days'] === null ? __('site.home.challenge_selector.unlimited') : $phase['maximum_trading_days'] }}</dd>
                                            </div>
                                            @if ($phase['leverage'])
                                                <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                                    <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.leverage') }}</dt>
                                                    <dd class="font-semibold text-white">{{ $phase['leverage'] }}</dd>
                                                </div>
                                            @endif
                                        </dl>
                                    </section>
                                @endforeach

                                <section class="surface-card rounded-[1.6rem] p-5">
                                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">{{ __('site.home.challenge_selector.phase_titles.funded') }}</p>
                                    <dl class="mt-4 space-y-3 text-sm">
                                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                            <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.profit_share') }}</dt>
                                            <dd class="font-semibold text-white">{{ $initialPlan['funded']['profit_split'] }}%</dd>
                                        </div>
                                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                            <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.payout_cycle') }}</dt>
                                            <dd class="font-semibold text-white">{{ str_replace(':days', (string) $initialPlan['funded']['payout_cycle_days'], __('site.home.challenge_selector.value_templates.days')) }}</dd>
                                        </div>
                                        @if ($initialPlan['funded']['first_withdrawal_days'])
                                            <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                                <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.first_withdrawal') }}</dt>
                                                <dd class="font-semibold text-white">{{ str_replace(':days', (string) $initialPlan['funded']['first_withdrawal_days'], __('site.home.challenge_selector.value_templates.after_days')) }}</dd>
                                            </div>
                                        @endif
                                        @if ($initialPlan['funded']['scaling_capital_percent'] && $initialPlan['funded']['scaling_interval_months'])
                                            <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                                <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.scaling') }}</dt>
                                                <dd class="max-w-[11rem] text-right font-semibold text-white">{{ str_replace([':percent', ':months'], [(string) $initialPlan['funded']['scaling_capital_percent'], (string) $initialPlan['funded']['scaling_interval_months']], __('site.home.challenge_selector.value_templates.scaling')) }}</dd>
                                            </div>
                                        @endif
                                        @if ($initialPlan['funded']['consistency_rule_required'])
                                            <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                                <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.consistency_rule') }}</dt>
                                                <dd class="font-semibold text-white">{{ __('site.home.challenge_selector.consistency_required') }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                </section>
                            </div>

                            <div class="mt-8 rounded-[1.8rem] border border-amber-400/18 bg-amber-400/10 p-5">
                                <p data-plan-note-title class="text-sm font-semibold text-amber-50">{{ __('site.home.challenge_selector.types.'.$defaultChallengeType.'.note_title') }}</p>
                                <p data-plan-note-body class="mt-3 text-sm leading-7 text-slate-200">{{ __('site.home.challenge_selector.types.'.$defaultChallengeType.'.note_body') }}</p>
                                <div class="mt-4 flex flex-wrap gap-3">
                                    <a href="{{ route('payout-policy') }}" class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                                        {{ __('site.home.challenge_selector.review_policy') }}
                                    </a>
                                    <a href="{{ route('faq') }}" class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                                        {{ __('site.home.challenge_selector.faq_link') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <section class="px-6 pt-20 lg:px-8">
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.95fr_1.05fr]">
            <div>
                <span class="section-label">{{ __('site.home.foundation.eyebrow') }}</span>
                <h2 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ __('site.home.foundation.title') }}</h2>
                <p class="mt-4 text-base leading-8 text-slate-300">{{ __('site.home.foundation.description') }}</p>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                @foreach (trans('site.home.foundation.cards') as $card)
                    <div class="surface-card rounded-[2rem] p-6">
                        <p class="text-lg font-semibold text-white">{{ $card['title'] }}</p>
                        <p class="mt-3 text-sm leading-7 text-slate-400">{{ $card['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="px-6 pt-20 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <span class="section-label">{{ __('site.home.workflow.eyebrow') }}</span>
            <h2 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ __('site.home.workflow.title') }}</h2>
            <div class="mt-10 grid gap-5 lg:grid-cols-3">
                @foreach (trans('site.home.workflow.items') as $index => $item)
                    <div class="surface-panel rounded-[2rem] p-6">
                        <div class="flex items-center gap-4">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full border border-amber-400/20 bg-amber-400/10 text-sm font-semibold text-amber-100">
                                {{ $index + 1 }}
                            </span>
                            <h3 class="text-xl font-semibold text-white">{{ $item['title'] }}</h3>
                        </div>
                        <p class="mt-5 text-sm leading-7 text-slate-300">{{ $item['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="px-6 pt-20 lg:px-8">
        <div class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[0.95fr_1.05fr]">
            <div>
                <span class="section-label">{{ __('site.checkout.eyebrow') }}</span>
                <h2 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ __('site.checkout.title') }}</h2>
                <p class="mt-4 text-base leading-8 text-slate-300">{{ __('site.checkout.description') }}</p>

                <div class="mt-8 surface-card rounded-[2rem] p-6">
                    <h3 class="text-lg font-semibold text-white">{{ __('site.checkout.supporting_title') }}</h3>
                    <p class="mt-3 text-sm leading-7 text-slate-400">{{ __('site.checkout.supporting_copy') }}</p>
                    <div class="mt-4 rounded-[1.6rem] border border-amber-400/18 bg-amber-400/10 px-4 py-4 text-sm leading-7 text-amber-50">
                        {{ __('site.checkout.kyc_notice') }}
                    </div>
                    <ul class="mt-5 space-y-3 text-sm text-slate-300">
                        @foreach (trans('site.checkout.helper_points') as $point)
                            <li class="rounded-2xl border border-white/6 bg-white/3 px-4 py-3">{{ $point }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="surface-panel rounded-[2rem] p-6 sm:p-8">
                <div class="rounded-[1.8rem] border border-white/8 bg-white/3 p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.checkout.current_selection') }}</p>
                    <h3 data-checkout-plan-title class="mt-4 text-2xl font-semibold text-white">
                        {{ __('site.home.challenge_selector.types.'.$defaultChallengeType.'.label') }} / {{ (int) ($initialPlan['account_size'] / 1000) }}K
                    </h3>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <span data-checkout-plan-price class="text-3xl font-semibold text-white">{{ $initialPrice }}</span>
                        <span data-checkout-plan-currency class="rounded-full border border-white/8 bg-white/4 px-4 py-2 text-xs font-semibold tracking-[0.24em] text-slate-200">{{ $defaultCurrency }}</span>
                    </div>
                    <p class="mt-4 text-sm leading-7 text-slate-400">{{ __('site.checkout.redirect_note') }}</p>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div class="surface-card rounded-[1.8rem] p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('site.checkout.client_data_title') }}</p>
                        <ul class="mt-4 space-y-3 text-sm text-slate-300">
                            <li>{{ __('site.checkout.full_name') }}</li>
                            <li>{{ __('site.checkout.email') }}</li>
                            <li>{{ __('site.checkout.street_address') }}</li>
                            <li>{{ __('site.checkout.city') }} / {{ __('site.checkout.postal_code') }}</li>
                            <li>{{ __('site.checkout.country') }}</li>
                        </ul>
                    </div>

                    <div class="surface-card rounded-[1.8rem] p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('site.checkout.payment_methods_title') }}</p>
                        <div class="mt-4 space-y-3 text-sm">
                            <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-emerald-100">
                                {{ __('site.checkout.buttons.stripe') }}
                            </div>
                            <div class="rounded-2xl border border-white/8 bg-white/4 px-4 py-3 text-slate-400">
                                {{ __('site.checkout.buttons.paypal') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 rounded-[1.8rem] border border-amber-400/18 bg-amber-400/10 px-4 py-4 text-sm leading-7 text-amber-50">
                    {{ __('site.checkout.agreement') }}
                </div>

                <a
                    href="{{ $defaultCheckoutUrl }}"
                    data-checkout-cta
                    data-checkout-base="{{ route('checkout.show') }}"
                    class="primary-cta mt-6 inline-flex rounded-full px-8 py-4 text-base font-semibold"
                >
                    {{ __('site.checkout.submit') }}
                </a>
            </div>
        </div>
    </section>
@endsection
