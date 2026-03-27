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
    $defaultCurrencyMeta = $currencies[$defaultCurrency] ?? [];
    $featureIcons = [
        <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75c-1.94 1.24-4.47 1.88-7.5 1.88v5.25c0 4.96 3.11 8.1 7.5 9.37 4.39-1.27 7.5-4.41 7.5-9.37V5.63c-3.03 0-5.56-.64-7.5-1.88Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 11.25 1.5 1.5 3-3.75" />
        </svg>
        SVG,
        <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 15.75h3.52c.48 0 .95.12 1.37.35l1.84 1.01c.42.23.9.35 1.37.35h2.02a1.88 1.88 0 0 0 0-3.75H10.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5h2.13c.8 0 1.56-.31 2.13-.88l1.17-1.17a2.25 2.25 0 0 1 1.59-.66H12a2.25 2.25 0 0 1 2.07 1.37l.68 1.61" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a2.25 2.25 0 1 1 0 4.5 2.25 2.25 0 0 1 0-4.5Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 10.5v6a.75.75 0 0 0 .75.75H4.5a.75.75 0 0 0 .75-.75v-6a.75.75 0 0 0-.75-.75H3a.75.75 0 0 0-.75.75Z" />
        </svg>
        SVG,
        <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5V4.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5h15" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 16.5v-3.75" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 16.5v-5.25" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m7.5 11.25 3-3 2.25 2.25 4.5-4.5" />
        </svg>
        SVG,
        <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m18.75 5.25-13.5 13.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 16.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
        </svg>
        SVG,
    ];
    $mobileFeatureIcons = $featureIcons;
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
    <section class="px-0 pt-0 sm:px-6 sm:pt-6 lg:px-8 lg:pt-12">
        <div class="mx-auto max-w-7xl">
            <div class="hero-shell rounded-none sm:rounded-[2.5rem]">
                <div class="mobile-hero relative isolate overflow-hidden sm:rounded-[2.5rem] lg:hidden">
                    <img
                        src="{{ asset('newfolder/mobile1.webp') }}"
                        alt="{{ __('site.home.hero_visual.image_alt') }}"
                        class="mobile-hero-media absolute inset-0 h-full w-full object-cover"
                        loading="eager"
                        decoding="async"
                    >
                    <div class="mobile-hero-shade absolute inset-0"></div>
                    <div class="mobile-hero-beam mobile-hero-beam-top" aria-hidden="true"></div>
                    <div class="mobile-hero-beam mobile-hero-beam-middle" aria-hidden="true"></div>
                    <div class="relative z-10 flex min-h-[44rem] flex-col justify-between px-5 pb-8 pt-10 text-center sm:px-8 sm:pb-10 sm:pt-12">
                        <div class="flex flex-1 flex-col items-center justify-center">
                            <h1 class="mobile-hero-title">
                                <span>{{ __('site.home.mobile_title.line_1') }}</span>
                                <span>{{ __('site.home.mobile_title.line_2') }}</span>
                            </h1>
                            <p class="mobile-hero-copy">
                                <span>{{ __('site.home.mobile_description.line_1') }}</span>
                                <span>{{ __('site.home.mobile_description.line_2') }}</span>
                            </p>
                        </div>

                        <div class="mobile-hero-footer">
                            <div class="mobile-hero-actions">
                                <a
                                    href="{{ $defaultCheckoutUrl }}"
                                    data-checkout-cta
                                    data-checkout-base="{{ route('checkout.show') }}"
                                    class="primary-cta mobile-hero-primary rounded-full px-4 py-4 text-base font-semibold"
                                >
                                    {{ __('site.home.primary_cta') }}
                                </a>
                                <div class="mobile-hero-trial-wrap">
                                    <a href="{{ route('trial.register') }}" class="ghost-cta mobile-hero-secondary rounded-full px-4 py-4 text-base font-semibold">
                                        <span class="mobile-hero-secondary-title">{{ __('site.home.free_trial_cta') }}</span>
                                        <span class="mobile-hero-trial-caption">{{ __('site.home.free_trial_caption') }}</span>
                                    </a>
                                </div>
                            </div>

                            <div class="mobile-hero-features">
                                @foreach (trans('site.home.feature_cards') as $card)
                                    <span class="mobile-hero-feature">
                                        <span class="mobile-hero-feature-icon">
                                            {!! $mobileFeatureIcons[$loop->index] ?? $featureIcons[$loop->index] ?? $featureIcons[0] !!}
                                        </span>
                                        <span>{{ $card }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hero-grid relative z-10 hidden items-center gap-10 px-6 py-8 sm:px-8 lg:grid lg:grid-cols-[0.92fr_minmax(0,1.08fr)] lg:gap-14 lg:px-10 lg:py-12">
                    <div class="max-w-3xl">
                        <span class="section-label">{{ __('site.home.eyebrow') }}</span>
                        <h1 class="hero-display-title mt-6 max-w-4xl text-4xl font-semibold leading-[1.02] sm:text-5xl lg:text-[4.5rem]">
                            {{ __('site.home.title') }}
                        </h1>
                        <p class="mt-6 max-w-2xl whitespace-pre-line text-base leading-8 text-slate-200 sm:text-lg">
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
                                <p class="mt-3 text-sm font-medium text-slate-400">{{ __('site.home.free_trial_caption') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="hero-visual-stage relative">
                        <div class="hero-visual-frame relative mx-auto w-full max-w-[42rem] overflow-hidden rounded-[2.2rem] border border-white/10 bg-slate-950/90 p-2 shadow-[0_34px_90px_rgba(2,6,23,0.52)]">
                            <div class="hero-visual-glow pointer-events-none absolute inset-x-[12%] bottom-[6%] h-20 rounded-full bg-amber-400/14 blur-3xl"></div>
                            <div class="hero-visual-image relative z-10 overflow-hidden rounded-[1.75rem] border border-white/8 bg-slate-950/95">
                                <picture>
                                    <source media="(min-width: 1024px)" srcset="{{ asset('trading123.png') }}">
                                    <img
                                        src="{{ asset('newfolder/mobile1.webp') }}"
                                        alt="{{ __('site.home.hero_visual.image_alt') }}"
                                        class="block aspect-[4/5] w-full object-cover object-center sm:aspect-[4/3] lg:aspect-[11/10]"
                                        loading="eager"
                                        decoding="async"
                                    >
                                </picture>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mx-auto mt-10 hidden max-w-7xl gap-4 lg:grid lg:grid-cols-4">
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
                                        <span class="flex items-center gap-2 text-sm font-semibold tracking-[0.22em] text-white">
                                            <span class="text-lg">{{ $currencyMeta['flag'] ?? '' }}</span>
                                            <span>{{ $currencyCode }}</span>
                                        </span>
                                        <span class="mt-2 block text-xs uppercase tracking-[0.22em] text-slate-400">
                                            {{ $currencyMeta['symbol'] ?? '' }} · {{ __('site.home.challenge_selector.currencies.'.$currencyCode) }}
                                        </span>
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
                                        <span class="rounded-full border border-white/8 bg-white/3 px-4 py-2 text-xs font-semibold tracking-[0.24em] text-slate-200">
                                            <span data-plan-currency-flag class="mr-2 text-sm">{{ $defaultCurrencyMeta['flag'] ?? '' }}</span>
                                            <span data-plan-currency-code>{{ $defaultCurrency }}</span>
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
                                        @if (! empty($initialPlan['funded']['profit_split_upgrade']))
                                            <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                                <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.profit_share_upgrade') }}</dt>
                                                <dd class="max-w-[11rem] text-right font-semibold text-white">
                                                    {{ str_replace([':percent', ':payouts'], [(string) $initialPlan['funded']['profit_split_upgrade']['profit_split'], (string) $initialPlan['funded']['profit_split_upgrade']['after_consecutive_payouts']], __('site.home.challenge_selector.value_templates.profit_split_upgrade')) }}
                                                </dd>
                                            </div>
                                        @endif
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
                        <span class="rounded-full border border-white/8 bg-white/4 px-4 py-2 text-xs font-semibold tracking-[0.24em] text-slate-200">
                            <span data-checkout-plan-currency-flag class="mr-2 text-sm">{{ $defaultCurrencyMeta['flag'] ?? '' }}</span>
                            <span data-checkout-plan-currency>{{ $defaultCurrency }}</span>
                        </span>
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
