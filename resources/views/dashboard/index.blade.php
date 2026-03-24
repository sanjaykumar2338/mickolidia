@extends('layouts.dashboard')

@section('title', __('site.dashboard.preview_title').' | '.__('site.meta.brand'))
@section('dashboard-title', __('site.dashboard.preview_title'))
@section('dashboard-subtitle', __('site.dashboard.preview_subtitle'))

@section('content')
    <div class="space-y-6">
        <x-consistency-banner :title="$consistencyBanner['title']" :message="$consistencyBanner['message']" :meta="$consistencyBanner['meta']" />

        <div class="grid gap-5 xl:grid-cols-4 md:grid-cols-2">
            @foreach ($summaryCards as $card)
                <x-stat-card :label="$card['label']" :value="$card['value']" :hint="$card['hint']" />
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <section class="surface-panel rounded-[2rem] p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.dashboard.overview.snapshot_title') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-white">{{ $primaryAccount['plan'] }}</h2>
                    </div>
                    <span class="rounded-full border border-emerald-400/20 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-100">
                        {{ $primaryAccount['status'] }}
                    </span>
                </div>

                <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-400">{{ __('site.dashboard.overview.snapshot_copy') }}</p>

                <dl class="mt-8 grid gap-4 md:grid-cols-2">
                    <div class="surface-card rounded-3xl p-5">
                        <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('site.dashboard.labels.reference') }}</dt>
                        <dd class="mt-3 text-lg font-semibold text-white">{{ $primaryAccount['reference'] }}</dd>
                    </div>
                    <div class="surface-card rounded-3xl p-5">
                        <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('site.dashboard.labels.platform') }}</dt>
                        <dd class="mt-3 text-lg font-semibold text-white">{{ $primaryAccount['platform'] }}</dd>
                    </div>
                    <div class="surface-card rounded-3xl p-5">
                        <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('site.dashboard.labels.stage') }}</dt>
                        <dd class="mt-3 text-lg font-semibold text-white">{{ $primaryAccount['stage'] }}</dd>
                    </div>
                    <div class="surface-card rounded-3xl p-5">
                        <dt class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('site.dashboard.labels.next_sync') }}</dt>
                        <dd class="mt-3 text-lg font-semibold text-white">{{ $primaryAccount['next_sync'] }}</dd>
                    </div>
                </dl>
            </section>

            <section class="space-y-6">
                <div class="surface-card rounded-[2rem] p-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.dashboard.overview.rules_title') }}</p>
                    <p class="mt-3 text-sm leading-7 text-slate-400">{{ __('site.dashboard.overview.rules_copy') }}</p>

                    <dl class="mt-6 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.dashboard.labels.target') }}</dt>
                            <dd class="font-semibold text-white">{{ $primaryPlan['phases'][0]['profit_target'] }}%</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.dashboard.labels.daily_loss') }}</dt>
                            <dd class="font-semibold text-white">{{ $primaryPlan['phases'][0]['daily_loss_limit'] }}%</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.dashboard.labels.max_loss') }}</dt>
                            <dd class="font-semibold text-white">{{ $primaryPlan['phases'][0]['max_loss_limit'] }}%</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.dashboard.labels.min_days') }}</dt>
                            <dd class="font-semibold text-white">{{ $primaryPlan['phases'][0]['minimum_trading_days'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.dashboard.labels.cycle') }}</dt>
                            <dd class="font-semibold text-white">{{ $primaryPlan['funded']['payout_cycle_days'] }} {{ __('site.home.days') }}</dd>
                        </div>
                        @if ($primaryPlan['phases'][0]['leverage'])
                            <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.leverage') }}</dt>
                                <dd class="font-semibold text-white">{{ $primaryPlan['phases'][0]['leverage'] }}</dd>
                            </div>
                        @endif
                        @if ($primaryPlan['funded']['first_withdrawal_days'])
                            <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                <dt class="text-slate-400">{{ __('site.home.challenge_selector.metrics.first_withdrawal') }}</dt>
                                <dd class="font-semibold text-white">{{ str_replace(':days', (string) $primaryPlan['funded']['first_withdrawal_days'], __('site.home.challenge_selector.value_templates.after_days')) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="surface-card rounded-[2rem] p-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.dashboard.overview.payout_title') }}</p>
                    <p class="mt-3 text-sm leading-7 text-slate-400">{{ __('site.dashboard.overview.payout_copy') }}</p>

                    <div class="mt-6 space-y-3">
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3 text-sm text-slate-300">
                            <strong class="font-semibold text-white">{{ __('site.dashboard.payouts.next_window') }}:</strong>
                            {{ $payoutSummary['next_window'] }}
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3 text-sm text-slate-300">
                            <strong class="font-semibold text-white">{{ __('site.dashboard.labels.eligible_profit') }}:</strong>
                            {{ $payoutSummary['eligible_profit'] }}
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3 text-sm text-slate-300">
                            {{ $payoutSummary['cycle_note'] }}
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="surface-panel rounded-[2rem] p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.dashboard.overview.settings_title') }}</p>
                    <p class="mt-3 text-sm leading-7 text-slate-400">{{ __('site.dashboard.overview.settings_copy') }}</p>
                </div>
                <a href="{{ route('dashboard.settings') }}" class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/25 hover:bg-white/6">
                    {{ __('site.dashboard.nav.settings') }}
                </a>
            </div>
        </div>
    </div>
@endsection
