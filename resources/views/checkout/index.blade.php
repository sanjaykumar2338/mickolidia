@extends('layouts.public')

@section('title', __('site.checkout.meta_title').' | '.__('site.meta.brand'))

@php
    $authUser = request()->user();
    $profile = $authUser?->profile;
    $profileCountryCode = $profile?->country ? array_search($profile->country, config('wolforix.checkout_countries', []), true) : null;
    $countryValue = old('country', $order?->country ?? $profileCountryCode);
    $fullNameValue = old('full_name', $order?->full_name ?? $authUser?->name);
    $emailValue = old('email', $order?->email ?? $authUser?->email);
    $streetAddressValue = old('street_address', $order?->street_address ?? $profile?->street_address);
    $cityValue = old('city', $order?->city ?? $profile?->city);
    $postalCodeValue = old('postal_code', $order?->postal_code ?? $profile?->postal_code);
    $launchPromoCodeValue = old('promo_code', $launchPromoCodeInput ?? '');
    $giveawayApplies = (bool) ($giveawayApplies ?? false);
    $promoCodeFeedbackState = $errors->has('promo_code') ? 'error' : ($launchPromoCode !== null || $giveawayApplies ? 'success' : 'idle');
    $promoCodeFeedbackMessage = $errors->first('promo_code')
        ?: ($giveawayApplies ? __('site.checkout.giveaway.no_payment_required') : ($launchPromoCode !== null ? __('site.checkout.promo_code_feedback.success') : __('site.checkout.promo_code_help')));
    $basePricingState = [
        'list_price' => number_format((float) $basePlan['list_price'], 2, '.', ''),
        'discounted_price' => number_format((float) $basePlan['discounted_price'], 2, '.', ''),
        'currency' => $selectedCurrency,
        'discount_enabled' => (bool) $basePlan['discount']['enabled'],
        'discount_badge' => (string) ($basePlan['discount']['badge'] ?? ''),
    ];
    $selectedPricingState = [
        'list_price' => number_format((float) $selectedPlan['list_price'], 2, '.', ''),
        'discounted_price' => number_format((float) $selectedPlan['discounted_price'], 2, '.', ''),
        'currency' => $selectedCurrency,
        'discount_enabled' => (bool) $selectedPlan['discount']['enabled'],
        'discount_badge' => (string) ($selectedPlan['discount']['badge'] ?? ''),
    ];
    $termsAgreement = trans('site.checkout.confirmations.terms_and_residency_html', [
        'terms_url' => route('terms'),
    ]);
    $refundAgreement = trans('site.checkout.confirmations.refund_policy_html', [
        'refund_url' => route('refund-policy'),
    ]);
    $paymentMethodHighlights = trans('site.checkout.payment_method_points');
    $paymentMethodHighlights = is_array($paymentMethodHighlights) ? $paymentMethodHighlights : [];
    $providerCardMeta = [
        'stripe' => [
            'logo' => '<span class="inline-flex items-center rounded-[1rem] border border-[#8c86ff]/18 bg-[#635bff] px-4 py-2 shadow-[0_10px_24px_rgba(99,91,255,0.18)]"><img src="'.asset('branding/stripe-logo.svg').'" alt="Stripe" class="block h-4 w-auto"></span>',
            'icon' => <<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h9m-9 4.5h9m-9 4.5h5.25" />
                </svg>
            SVG,
            'card_classes' => 'border-[#8c86ff]/22 bg-[#635bff]/10 peer-checked:border-[#9f9bff]/46 peer-checked:bg-[#635bff]/16',
            'badge_classes' => 'border-[#8c86ff]/20 bg-[#635bff]/12 text-[#e4e5ff]',
            'cta_classes' => 'border-[#8c86ff]/18 bg-[#635bff]/12 text-[#eef0ff]',
        ],
        'paypal' => [
            'logo' => '<span class="inline-flex items-center rounded-[1rem] border border-white/10 bg-white px-3 py-2 shadow-[0_10px_24px_rgba(2,6,23,0.16)]"><img src="'.asset('branding/paypal-logo.png').'" alt="PayPal" class="block h-5 w-auto"></span>',
            'icon' => <<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5h7.5a3 3 0 0 1 0 6H9.75l-1.5 4.5H5.25l2.1-8.4A2.63 2.63 0 0 1 9.9 7.5Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 18h4.2a2.85 2.85 0 0 0 2.79-2.22l.81-3.78" />
                </svg>
            SVG,
            'card_classes' => 'border-[#4db2ff]/16 bg-[#0f3b81]/10 peer-checked:border-[#61c0ff]/34 peer-checked:bg-[#0f3b81]/14',
            'badge_classes' => 'border-[#4db2ff]/16 bg-[#0f3b81]/12 text-[#d9f0ff]',
            'cta_classes' => 'border-[#4db2ff]/14 bg-[#0f3b81]/12 text-[#e6f5ff]',
        ],
    ];
@endphp

@section('content')
    <section class="px-6 pb-6 pt-10 lg:px-8 lg:pt-14">
        <div class="mx-auto max-w-7xl">
            <span class="section-label">{{ __('site.checkout.eyebrow') }}</span>
            <div class="mt-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <h1 class="text-3xl font-semibold text-white sm:text-4xl">{{ __('site.checkout.page_title') }}</h1>
                    <p class="mt-4 text-base leading-8 text-slate-300">{{ __('site.checkout.page_description') }}</p>
                </div>
                <div class="gold-pill rounded-full px-4 py-2 text-sm font-medium">
                    {{ __('site.checkout.secure_badge') }}
                </div>
            </div>
        </div>
    </section>

    <section class="px-6 pb-12 lg:px-8">
        <div class="mx-auto grid max-w-7xl gap-8 xl:grid-cols-[0.88fr_1.12fr]">
            <aside class="space-y-6">
                <div class="surface-panel rounded-[2rem] p-6" data-checkout-pricing>
                    <script type="application/json" data-checkout-base-pricing>@json($basePricingState)</script>
                    <script type="application/json" data-checkout-selected-pricing>@json($selectedPricingState)</script>
                    <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.checkout.order_summary') }}</p>
                    <h2 class="mt-4 text-3xl font-semibold text-white">
                        {{ __('site.home.challenge_selector.types.'.$selectedChallengeType.'.label') }} / {{ (int) ($selectedAccountSize / 1000) }}K
                    </h2>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <span data-checkout-discount-badge class="{{ $selectedPlan['discount']['enabled'] ? '' : 'hidden ' }}gold-pill rounded-full px-4 py-2 text-xs font-semibold">{{ __('site.home.challenge_selector.discount_badge') }}</span>
                        <span class="rounded-full border border-white/8 bg-white/4 px-4 py-2 text-xs font-semibold tracking-[0.24em] text-slate-200">
                            {{ $selectedCurrency }}
                        </span>
                    </div>

                    <p class="mt-5 text-4xl font-semibold text-white">
                        <span data-checkout-final-price>{{ number_format((float) $selectedPlan['discounted_price'], 2) }}</span>
                        <span data-checkout-currency class="text-lg font-medium text-slate-400">{{ $selectedCurrency }}</span>
                    </p>

                    <p data-checkout-original-wrap class="{{ $selectedPlan['discount']['enabled'] ? '' : 'hidden ' }}mt-3 text-sm text-slate-400">
                        {{ __('site.home.challenge_selector.original_price') }}
                        <span data-checkout-original-price class="ml-2 font-semibold line-through">{{ number_format((float) $selectedPlan['list_price'], 2) }} {{ $selectedCurrency }}</span>
                    </p>

                    <dl class="mt-6 space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-3 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.profit_share') }}</dt>
                            <dd class="text-right font-semibold text-white">
                                <span class="block">{{ $selectedPlan['funded']['profit_split'] }}%</span>
                                @if (! empty($selectedPlan['funded']['profit_split_upgrade']))
                                    <span class="mt-1 block text-xs font-medium text-amber-200">
                                        {{ str_replace([':percent', ':payouts'], [(string) $selectedPlan['funded']['profit_split_upgrade']['profit_split'], (string) $selectedPlan['funded']['profit_split_upgrade']['after_consecutive_payouts']], __('site.home.challenge_selector.value_templates.profit_split_upgrade')) }}
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.daily_loss') }}</dt>
                            <dd class="font-semibold text-white">{{ $selectedPlan['daily_loss_limit'] }}%</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.total_loss') }}</dt>
                            <dd class="font-semibold text-white">{{ $selectedPlan['max_loss_limit'] }}%</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.minimum_days') }}</dt>
                            <dd class="font-semibold text-white">{{ $selectedPlan['minimum_trading_days'] }}</dd>
                        </div>
                    </dl>
                </div>

            </aside>

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

                <form method="POST" action="{{ route('checkout.store') }}" class="space-y-6">
                    @csrf

                    @if ($order)
                        <input type="hidden" name="order" value="{{ $order->order_number }}">
                    @endif

                    <input type="hidden" name="challenge_type" value="{{ $selectedChallengeType }}">
                    <input type="hidden" name="account_size" value="{{ $selectedAccountSize }}">
                    <input type="hidden" name="currency" value="{{ $selectedCurrency }}">

                    <div
                        class="rounded-[1.8rem] border border-white/8 bg-white/3 p-5"
                        data-checkout-promo
                        data-preview-url="{{ route('checkout.promo.preview') }}"
                        data-challenge-type="{{ $selectedChallengeType }}"
                        data-account-size="{{ $selectedAccountSize }}"
                        data-currency="{{ $selectedCurrency }}"
                        data-help-message="{{ __('site.checkout.promo_code_help') }}"
                        data-success-message="{{ __('site.checkout.promo_code_feedback.success') }}"
                        data-giveaway-message="{{ __('site.checkout.giveaway.no_payment_required') }}"
                        data-invalid-message="{{ __('site.checkout.promo_code_feedback.invalid') }}"
                        data-applied-code="{{ $giveawayApplies ? $launchPromoCodeValue : ($launchPromoCode ?? '') }}"
                        data-payment-required="{{ $giveawayApplies ? 'false' : 'true' }}"
                    >
                        <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.checkout.promo_code_label') }}</p>
                        <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                            <label class="block flex-1">
                                <span class="sr-only">{{ __('site.checkout.promo_code_label') }}</span>
                                <input
                                    type="text"
                                    name="promo_code"
                                    value="{{ $launchPromoCodeValue }}"
                                    data-promo-code-input
                                    class="w-full rounded-2xl border {{ $errors->has('promo_code') ? 'border-rose-400/30' : ($launchPromoCode !== null ? 'border-emerald-400/24' : 'border-white/10') }} bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                    placeholder="{{ __('site.checkout.promo_code_placeholder') }}"
                                    autocomplete="off"
                                >
                            </label>
                            <button
                                type="button"
                                data-promo-code-apply
                                class="inline-flex min-h-[3.25rem] items-center justify-center rounded-2xl border border-white/10 bg-white/4 px-6 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/8 sm:min-w-[8rem]"
                            >
                                {{ __('site.checkout.promo_code_apply') }}
                            </button>
                        </div>
                        <p
                            data-promo-code-feedback
                            data-feedback-state="{{ $promoCodeFeedbackState }}"
                            class="mt-4 text-sm leading-6 {{ $promoCodeFeedbackState === 'error' ? 'text-rose-100' : ($promoCodeFeedbackState === 'success' ? 'text-emerald-100' : 'text-slate-400') }}"
                        >
                            {{ $promoCodeFeedbackMessage }}
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.full_name') }}</span>
                            <input
                                type="text"
                                name="full_name"
                                value="{{ $fullNameValue }}"
                                class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                placeholder="{{ __('site.checkout.full_name') }}"
                            >
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.email') }}</span>
                            <input
                                type="email"
                                name="email"
                                value="{{ $emailValue }}"
                                class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                placeholder="{{ __('trader@example.com') }}"
                            >
                        </label>
                    </div>

                    <div class="rounded-[1.8rem] border border-white/8 bg-white/3 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.checkout.billing_title') }}</p>
                        <div class="mt-5 grid gap-5 md:grid-cols-2">
                            <label class="block md:col-span-2">
                                <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.street_address') }}</span>
                                <input
                                    type="text"
                                    name="street_address"
                                    value="{{ $streetAddressValue }}"
                                    class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                    placeholder="{{ __('site.checkout.street_address') }}"
                                >
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.city') }}</span>
                                <input
                                    type="text"
                                    name="city"
                                    value="{{ $cityValue }}"
                                    class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                    placeholder="{{ __('site.checkout.city') }}"
                                >
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.postal_code') }}</span>
                                <input
                                    type="text"
                                    name="postal_code"
                                    value="{{ $postalCodeValue }}"
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
                                        <option value="{{ $code }}" @selected($countryValue === $code)>{{ $country }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>

                    <div
                        class="{{ $giveawayApplies ? 'hidden ' : '' }}rounded-[1.8rem] border border-white/8 bg-white/3 p-5"
                        data-checkout-payment-section
                    >
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-300">{{ __('site.checkout.payment_methods_title') }}</p>
                        <p class="mt-3 text-sm leading-6 text-slate-400">{{ __('site.checkout.payment_methods_subtitle') }}</p>
                        <div class="mt-5 grid gap-4 xl:grid-cols-2">
                            @foreach ($paymentProviders as $providerKey => $provider)
                                @php($cardMeta = $providerCardMeta[$providerKey] ?? $providerCardMeta['stripe'])
                                <label class="block">
                                    <input
                                        type="radio"
                                        name="payment_provider"
                                        value="{{ $providerKey }}"
                                        class="peer sr-only"
                                        @checked(old('payment_provider', config('wolforix.payments.default_provider')) === $providerKey)
                                        @disabled(! $provider['enabled'])
                                        data-provider-enabled="{{ $provider['enabled'] ? 'true' : 'false' }}"
                                    >
                                    <span class="block rounded-[1.65rem] border border-white/8 px-5 py-5 transition peer-checked:shadow-[0_24px_52px_rgba(2,6,23,0.22)] {{ $cardMeta['card_classes'] }} {{ $provider['enabled'] ? 'cursor-pointer hover:border-white/16 hover:bg-white/5' : 'cursor-not-allowed opacity-60' }}">
                                        <span class="flex flex-wrap items-start justify-between gap-3">
                                            <span class="flex min-w-0 items-center gap-3">
                                                {!! $cardMeta['logo'] !!}
                                                <span class="block min-w-0">
                                                    <span class="block text-xl font-semibold text-white">{{ __('site.checkout.providers.'.$providerKey.'.label') }}</span>
                                                    @if ($providerKey === 'stripe')
                                                        <span class="mt-1 inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.2em] {{ $cardMeta['badge_classes'] }}">
                                                            {{ __('site.checkout.provider_recommended') }}
                                                        </span>
                                                    @endif
                                                </span>
                                            </span>
                                            <span class="inline-flex max-w-full shrink-0 items-center justify-center self-start rounded-full border border-white/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] {{ $provider['enabled'] ? 'text-white/80' : 'text-slate-400' }}">
                                                {{ $provider['enabled'] ? __('site.checkout.provider_available') : __('site.checkout.provider_coming_soon') }}
                                            </span>
                                        </span>
                                        <span class="mt-5 block text-sm font-medium text-slate-100">{{ __('site.checkout.providers.'.$providerKey.'.summary') }}</span>
                                        <span class="mt-2 block text-sm leading-6 text-slate-400">{{ __('site.checkout.providers.'.$providerKey.'.supporting') }}</span>
                                        <span class="mt-5 inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold {{ $cardMeta['cta_classes'] }}">
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-white/10 bg-white/8 text-white/85">
                                                {!! $cardMeta['icon'] !!}
                                            </span>
                                            <span>{{ __('site.checkout.providers.'.$providerKey.'.cta') }}</span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @if ($paymentMethodHighlights !== [])
                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                @foreach ($paymentMethodHighlights as $highlight)
                                    <div class="flex items-center gap-3 rounded-[1.35rem] border border-white/8 bg-black/15 px-4 py-3 text-sm text-slate-200">
                                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-amber-400/20 bg-amber-400/10 text-amber-200">
                                            @if ($loop->index === 0)
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75c-1.94 1.24-4.47 1.88-7.5 1.88v5.25c0 4.96 3.11 8.1 7.5 9.37 4.39-1.27 7.5-4.41 7.5-9.37V5.63c-3.03 0-5.56-.64-7.5-1.88Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 11.25 1.5 1.5 3-3.75" />
                                                </svg>
                                            @elseif ($loop->index === 1)
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2.25" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75 9 17.25l10.5-10.5" />
                                                </svg>
                                            @endif
                                        </span>
                                        <span>{{ $highlight }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <p class="mt-4 text-xs leading-6 text-slate-500">{{ __('site.checkout.trust_message') }}</p>
                    </div>

                    <div
                        class="{{ $giveawayApplies ? '' : 'hidden ' }}rounded-[1.8rem] border border-emerald-400/20 bg-emerald-500/10 p-5 text-sm leading-6 text-emerald-50"
                        data-checkout-free-message
                    >
                        {{ __('site.checkout.giveaway.no_payment_required') }}
                    </div>

                    <div class="rounded-[1.8rem] border border-white/8 bg-white/3 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.checkout.confirmation_title') }}</p>
                        <div class="mt-5 space-y-4">
                            <label class="flex items-start gap-4 rounded-[1.5rem] border px-4 py-4 {{ $errors->has('accept_terms_and_residency') ? 'border-rose-400/30 bg-rose-500/10' : 'border-white/8 bg-black/15' }}">
                                <input
                                    type="checkbox"
                                    name="accept_terms_and_residency"
                                    value="1"
                                    required
                                    @checked(old('accept_terms_and_residency'))
                                    class="mt-1 h-5 w-5 rounded border-white/20 bg-black/40 text-amber-400 focus:ring-amber-300"
                                >
                                <span class="text-sm leading-7 text-slate-200">{!! $termsAgreement !!}</span>
                            </label>

                            <label class="flex items-start gap-4 rounded-[1.5rem] border px-4 py-4 {{ $errors->has('accept_refund_policy') ? 'border-rose-400/30 bg-rose-500/10' : 'border-white/8 bg-black/15' }}">
                                <input
                                    type="checkbox"
                                    name="accept_refund_policy"
                                    value="1"
                                    required
                                    @checked(old('accept_refund_policy'))
                                    class="mt-1 h-5 w-5 rounded border-white/20 bg-black/40 text-amber-400 focus:ring-amber-300"
                                >
                                <span class="text-sm leading-7 text-slate-200">{!! $refundAgreement !!}</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 sm:flex-row">
                        <button type="submit" class="primary-cta rounded-full px-8 py-4 text-base font-semibold" data-checkout-submit data-payment-label="{{ __('site.checkout.submit') }}" data-free-label="{{ __('site.checkout.giveaway.submit') }}">
                            {{ $giveawayApplies ? __('site.checkout.giveaway.submit') : __('site.checkout.submit') }}
                        </button>
                        <a href="{{ route('home') }}#plans" class="inline-flex rounded-full border border-white/10 px-6 py-4 text-sm font-semibold text-white transition hover:border-white/25 hover:bg-white/6">
                            {{ __('site.checkout.back_to_plans') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
