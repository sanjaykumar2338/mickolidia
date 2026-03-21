@extends('layouts.dashboard')

@section('title', __('site.dashboard.accounts_page.title').' | '.__('site.meta.brand'))
@section('dashboard-title', __('site.dashboard.accounts_page.title'))
@section('dashboard-subtitle', __('site.dashboard.accounts_page.subtitle'))

@section('content')
    <div class="space-y-6">
        <x-consistency-banner :title="$consistencyBanner['title']" :message="$consistencyBanner['message']" :meta="$consistencyBanner['meta']" />

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
                        <p class="mt-4 text-sm text-slate-400">{{ __('site.home.plans.entry_fee') }} {{ $currencyPrefix }}{{ number_format($plan['entry_fee'], 0) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
