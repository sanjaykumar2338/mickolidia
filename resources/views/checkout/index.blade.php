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
                <div class="surface-panel rounded-[2rem] p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.checkout.order_summary') }}</p>
                    <h2 class="mt-4 text-3xl font-semibold text-white">
                        {{ __('site.home.challenge_selector.types.'.$selectedChallengeType.'.label') }} / {{ (int) ($selectedAccountSize / 1000) }}K
                    </h2>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        @if ($selectedPlan['discount']['enabled'])
                            <span class="gold-pill rounded-full px-4 py-2 text-xs font-semibold">{{ __('site.home.challenge_selector.discount_badge') }}</span>
                        @endif
                        <span class="rounded-full border border-white/8 bg-white/4 px-4 py-2 text-xs font-semibold tracking-[0.24em] text-slate-200">
                            {{ $selectedCurrency }}
                        </span>
                    </div>

                    <p class="mt-5 text-4xl font-semibold text-white">
                        {{ number_format((float) $selectedPlan['discounted_price'], 2) }}
                        <span class="text-lg font-medium text-slate-400">{{ $selectedCurrency }}</span>
                    </p>

                    @if ($selectedPlan['discount']['enabled'])
                        <p class="mt-3 text-sm text-slate-400">
                            {{ __('site.home.challenge_selector.original_price') }}
                            <span class="ml-2 font-semibold line-through">{{ number_format((float) $selectedPlan['list_price'], 2) }} {{ $selectedCurrency }}</span>
                        </p>
                    @endif

                    <dl class="mt-6 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.profit_share') }}</dt>
                            <dd class="font-semibold text-white">{{ $selectedPlan['funded']['profit_split'] }}%</dd>
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

                <div class="surface-card rounded-[2rem] p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.checkout.payment_methods_title') }}</p>
                    <div class="mt-5 space-y-3">
                        @foreach ($paymentProviders as $providerKey => $provider)
                            <div class="rounded-[1.6rem] border {{ $provider['enabled'] ? 'border-emerald-400/20 bg-emerald-500/10' : 'border-white/8 bg-white/3' }} px-4 py-4">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold {{ $provider['enabled'] ? 'text-emerald-100' : 'text-white' }}">{{ __('site.checkout.providers.'.$providerKey.'.label') }}</p>
                                        <p class="mt-1 text-sm leading-6 {{ $provider['enabled'] ? 'text-emerald-50/80' : 'text-slate-400' }}">{{ __('site.checkout.providers.'.$providerKey.'.description') }}</p>
                                    </div>
                                    <span class="rounded-full border border-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] {{ $provider['enabled'] ? 'text-emerald-100' : 'text-slate-400' }}">
                                        {{ $provider['enabled'] ? __('site.checkout.provider_available') : __('site.checkout.provider_coming_soon') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
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
                                placeholder="trader@example.com"
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

                    <div class="rounded-[1.8rem] border border-white/8 bg-white/3 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.checkout.payment_methods_title') }}</p>
                        <div class="mt-5 space-y-3">
                            @foreach ($paymentProviders as $providerKey => $provider)
                                <label class="block">
                                    <input
                                        type="radio"
                                        name="payment_provider"
                                        value="{{ $providerKey }}"
                                        class="peer sr-only"
                                        @checked(old('payment_provider', config('wolforix.payments.default_provider')) === $providerKey)
                                        @disabled(! $provider['enabled'])
                                    >
                                    <span class="flex rounded-[1.6rem] border border-white/8 bg-white/3 px-4 py-4 transition peer-checked:border-amber-300/30 peer-checked:bg-amber-400/10 {{ $provider['enabled'] ? 'cursor-pointer hover:border-white/16 hover:bg-white/5' : 'cursor-not-allowed opacity-60' }}">
                                        <span class="flex-1">
                                            <span class="block text-sm font-semibold text-white">{{ __('site.checkout.providers.'.$providerKey.'.label') }}</span>
                                            <span class="mt-2 block text-sm leading-6 text-slate-400">{{ __('site.checkout.providers.'.$providerKey.'.description') }}</span>
                                        </span>
                                        <span class="ml-4 self-start rounded-full border border-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">
                                            {{ $provider['enabled'] ? __('site.checkout.provider_available') : __('site.checkout.provider_coming_soon') }}
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
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

                    <div class="flex flex-col gap-4 sm:flex-row">
                        <button type="submit" class="primary-cta rounded-full px-8 py-4 text-base font-semibold">
                            {{ __('site.checkout.submit') }}
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
