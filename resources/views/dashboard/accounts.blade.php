@extends('layouts.dashboard')

@section('title', __('site.dashboard.accounts_page.title').' | '.__('site.meta.brand'))
@section('dashboard-title', __('site.dashboard.accounts_page.title'))
@section('dashboard-subtitle', __('site.dashboard.accounts_page.subtitle'))

@section('content')
    <div class="space-y-6">
        <x-consistency-banner :title="$consistencyBanner['title']" :message="$consistencyBanner['message']" :meta="$consistencyBanner['meta']" />

        @if ($purchasedChallenges->isNotEmpty())
            <div class="surface-panel rounded-[2rem] p-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.dashboard.purchases.title') }}</p>
                        <p class="mt-3 text-sm leading-7 text-slate-400">{{ __('site.dashboard.purchases.subtitle') }}</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-5 lg:grid-cols-2">
                    @foreach ($purchasedChallenges as $purchase)
                        <article class="surface-card rounded-[1.8rem] p-5">
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold tracking-[0.22em] text-amber-300">{{ $purchase['plan'] }}</p>
                                    <h2 class="mt-2 text-xl font-semibold text-white">{{ $purchase['reference'] }}</h2>
                                </div>
                                <span class="rounded-full border border-white/8 bg-white/4 px-4 py-2 text-sm text-slate-200">{{ $purchase['account_status'] }}</span>
                            </div>

                            <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('site.dashboard.purchases.amount') }}</dt>
                                    <dd class="mt-2 font-semibold text-white">{{ $purchase['amount'] }}</dd>
                                </div>
                                <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('site.dashboard.purchases.payment_provider') }}</dt>
                                    <dd class="mt-2 font-semibold text-white">{{ $purchase['payment_provider'] }}</dd>
                                </div>
                                <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('site.dashboard.purchases.payment_status') }}</dt>
                                    <dd class="mt-2 font-semibold text-white">{{ $purchase['payment_status'] }}</dd>
                                </div>
                                <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('site.dashboard.purchases.order_date') }}</dt>
                                    <dd class="mt-2 font-semibold text-white">{{ $purchase['created_at'] }}</dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid gap-5 lg:grid-cols-2">
            @foreach ($accounts as $account)
                <article class="surface-panel rounded-[2rem] p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold tracking-[0.24em] text-amber-300">{{ $account['plan'] }}</p>
                            <h2 class="mt-2 text-2xl font-semibold text-white">{{ $account['reference'] }}</h2>
                        </div>
                        <span class="rounded-full border border-white/8 bg-white/4 px-4 py-2 text-sm text-slate-200">{{ $account['status'] }}</span>
                    </div>

                    <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div class="surface-card rounded-3xl p-5">
                            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('site.dashboard.labels.stage') }}</dt>
                            <dd class="mt-3 text-lg font-semibold text-white">{{ $account['stage'] }}</dd>
                        </div>
                        <div class="surface-card rounded-3xl p-5">
                            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('site.dashboard.cards.balance') }}</dt>
                            <dd class="mt-3 text-lg font-semibold text-white">{{ $account['balance'] }}</dd>
                        </div>
                        <div class="surface-card rounded-3xl p-5 sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('site.dashboard.labels.progress') }}</dt>
                            <dd class="mt-3 text-lg font-semibold text-white">{{ $account['progress'] }}</dd>
                            <div class="mt-4 h-2 rounded-full bg-white/6">
                                <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-sky-400" style="width: {{ $account['progress'] }}"></div>
                            </div>
                        </div>
                    </dl>
                </article>
            @endforeach
        </div>

        <div class="surface-card rounded-[2rem] p-6">
            <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.home.plans.eyebrow') }}</p>
            <div class="mt-6 grid gap-4 xl:grid-cols-4 md:grid-cols-2">
                @foreach (config('wolforix.challenge_plans') as $plan)
                    @php
                        $currencyPrefix = match ($plan['currency']) {
                            'USD' => '$',
                            'EUR' => '€',
                            default => $plan['currency'].' ',
                        };
                    @endphp
                    <div class="rounded-3xl border border-white/8 bg-white/3 p-5">
                        <p class="text-sm font-semibold tracking-[0.24em] text-amber-300">{{ $plan['name'] }}</p>
                        <p class="mt-3 text-3xl font-semibold text-white">{{ $currencyPrefix }}{{ number_format($plan['account_size']) }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @if ($plan['discount']['enabled'])
                                <span class="gold-pill rounded-full px-3 py-1.5 text-[11px] font-semibold">{{ __('site.home.challenge_selector.discount_badge') }}</span>
                            @endif
                            <span class="text-sm font-semibold text-white">{{ __('site.home.challenge_selector.current_price') }} {{ $currencyPrefix }}{{ number_format($plan['entry_fee'], 0) }}</span>
                        </div>
                        @if ($plan['discount']['enabled'])
                            <p class="mt-3 text-sm text-slate-400">
                                {{ __('site.home.challenge_selector.original_price') }}
                                <span class="ml-2 font-semibold line-through">{{ $currencyPrefix }}{{ number_format($plan['list_price'], 0) }}</span>
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
