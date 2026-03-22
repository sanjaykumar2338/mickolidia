@extends('layouts.public')

@section('title', __('site.meta.default_title'))

@php
    $initialPlan = $defaultChallengeType !== null && $defaultChallengeSize !== null
        ? $challengeCatalog[$defaultChallengeType]['plans'][(int) $defaultChallengeSize]
        : null;
    $formatMoney = static function (int|float $amount, string $currency = 'USD'): string {
        return match ($currency) {
            'USD' => '$'.number_format($amount, 0),
            'EUR' => '€'.number_format($amount, 0),
            default => $currency.' '.number_format($amount, 0),
        };
    };
    $initialPrice = $initialPlan !== null ? $formatMoney($initialPlan['discounted_price'], $initialPlan['currency']) : '';
    $initialListPrice = $initialPlan !== null ? $formatMoney($initialPlan['list_price'], $initialPlan['currency']) : '';
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

                <div class="mt-8 flex flex-wrap gap-4">
                    <a href="#plans" class="primary-cta rounded-full px-6 py-3 text-sm font-semibold">
                        {{ __('site.home.primary_cta') }}
                    </a>
                    <a href="{{ route('dashboard') }}" class="rounded-full border border-white/10 px-6 py-3 text-sm font-semibold text-white transition hover:border-white/25 hover:bg-white/6">
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
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-amber-400/20 bg-amber-400/10 text-sm font-semibold text-amber-200">
                        {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
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
                    data-default-type="{{ $defaultChallengeType }}"
                    data-default-size="{{ $defaultChallengeSize }}"
                    data-unlimited-label="{{ __('site.home.challenge_selector.unlimited') }}"
                    data-days-label="{{ __('site.home.days') }}"
                    class="mt-10"
                >
                    <script type="application/json" data-challenge-catalog>@json($challengeCatalog)</script>
                    <script type="application/json" data-challenge-ui>@json($challengeUi)</script>

                    <div class="grid gap-6 xl:grid-cols-[0.92fr_1.08fr]">
                        <div class="surface-panel rounded-[2rem] p-6">
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

                                <a href="#checkout" class="primary-cta rounded-full px-6 py-3 text-sm font-semibold">
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

    <section id="checkout" class="px-6 pt-20 lg:px-8">
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
                @if ($errors->any())
                    <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-4 text-sm text-rose-100">
                        <ul class="space-y-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('challenge.checkout.store') }}" class="space-y-5">
                    @csrf

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.full_name') }}</span>
                            <input
                                type="text"
                                name="full_name"
                                value="{{ old('full_name') }}"
                                class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                placeholder="{{ __('site.checkout.full_name') }}"
                            >
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.email') }}</span>
                            <input
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                placeholder="trader@example.com"
                            >
                        </label>
                    </div>

                    <div class="rounded-[1.8rem] border border-white/8 bg-white/3 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.checkout.client_data_title') }}</p>
                        <div class="mt-5 grid gap-5 md:grid-cols-2">
                            <label class="block md:col-span-2">
                                <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.street_address') }}</span>
                                <input
                                    type="text"
                                    name="street_address"
                                    value="{{ old('street_address') }}"
                                    class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                    placeholder="{{ __('site.checkout.street_address') }}"
                                >
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.city') }}</span>
                                <input
                                    type="text"
                                    name="city"
                                    value="{{ old('city') }}"
                                    class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                    placeholder="{{ __('site.checkout.city') }}"
                                >
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.postal_code') }}</span>
                                <input
                                    type="text"
                                    name="postal_code"
                                    value="{{ old('postal_code') }}"
                                    class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                    placeholder="{{ __('site.checkout.postal_code') }}"
                                >
                            </label>

                            <label class="block md:col-span-2">
                                <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.country') }}</span>
                                <select
                                    name="country"
                                    class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition focus:border-amber-400/35"
                                >
                                    <option value="">{{ __('site.checkout.select_country') }}</option>
                                    @foreach ($checkoutCountries as $code => $country)
                                        <option value="{{ $code }}" @selected(old('country') === $code)>{{ $country }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.plan') }}</span>
                            <select
                                name="plan"
                                data-checkout-plan-select
                                class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition focus:border-amber-400/35"
                            >
                                <option value="">{{ __('site.checkout.select_plan') }}</option>
                                @foreach ($challengeCatalog as $challengeTypeKey => $challengeType)
                                    <optgroup label="{{ __('site.home.challenge_selector.types.'.$challengeTypeKey.'.label') }}">
                                        @foreach ($challengeType['plans'] as $size => $plan)
                                            <option value="{{ $plan['slug'] }}" @selected(old('plan', $initialPlan['slug'] ?? null) === $plan['slug'])>
                                                {{ __('site.home.challenge_selector.types.'.$challengeTypeKey.'.label') }} - {{ (int) ($size / 1000) }}K
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.platform') }}</span>
                            <input
                                type="text"
                                value="{{ __('site.checkout.platform_value') }}"
                                readonly
                                class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-slate-300 outline-none"
                            >
                        </label>
                    </div>

                    <label class="flex items-start gap-3 rounded-2xl border border-amber-400/18 bg-amber-400/10 px-4 py-4">
                        <input
                            type="checkbox"
                            name="accept_terms"
                            value="1"
                            @checked(old('accept_terms'))
                            class="mt-1 h-4 w-4 rounded border-white/20 bg-black/40 text-amber-400 focus:ring-amber-300"
                        >
                        <span class="text-sm leading-7 text-amber-50">{{ __('site.checkout.agreement') }}</span>
                    </label>

                    <div class="grid gap-3 md:grid-cols-2">
                        <button type="submit" class="rounded-full bg-amber-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-300">
                            {{ __('site.checkout.submit') }}
                        </button>
                        <button type="button" disabled class="cursor-not-allowed rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-slate-400">
                            {{ __('site.checkout.buttons.stripe') }}
                        </button>
                    </div>

                    <button type="button" disabled class="w-full cursor-not-allowed rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-slate-400">
                        {{ __('site.checkout.buttons.paypal') }}
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection
