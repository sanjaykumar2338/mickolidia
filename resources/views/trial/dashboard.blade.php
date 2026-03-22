@extends('layouts.public')

@section('title', __('site.trial.dashboard.title').' | '.__('site.meta.brand'))

@php
    $metrics = [
        ['label' => __('site.trial.dashboard.metrics.balance'), 'value' => '$'.number_format((float) $trialAccount->balance, 2)],
        ['label' => __('site.trial.dashboard.metrics.equity'), 'value' => '$'.number_format((float) $trialAccount->equity, 2)],
        ['label' => __('site.trial.dashboard.metrics.daily_drawdown'), 'value' => '$'.number_format((float) $trialAccount->daily_drawdown, 2)],
        ['label' => __('site.trial.dashboard.metrics.max_drawdown'), 'value' => '$'.number_format((float) $trialAccount->max_drawdown, 2)],
        ['label' => __('site.trial.dashboard.metrics.profit_loss'), 'value' => '$'.number_format((float) $trialAccount->profit_loss, 2)],
    ];
@endphp

@section('content')
    <section class="px-6 pt-12 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="section-label">{{ __('site.trial.eyebrow') }}</span>
                    <h1 class="mt-5 text-4xl font-semibold text-white sm:text-5xl">{{ __('site.trial.dashboard.title') }}</h1>
                    <p class="mt-4 max-w-3xl text-base leading-8 text-slate-300">{{ __('site.trial.dashboard.description') }}</p>
                </div>
                <div class="gold-pill rounded-full px-4 py-2 text-sm font-medium">
                    {{ $trialAccount->account_reference }}
                </div>
            </div>

            <div class="mt-8 rounded-[1.8rem] border border-amber-400/18 bg-amber-400/10 p-5 text-sm leading-7 text-amber-50">
                <p class="font-semibold">{{ __('site.trial.dashboard.banner_title') }}</p>
                <p class="mt-2">{{ __('site.trial.dashboard.banner_copy') }}</p>
            </div>

            @if ($milestoneMessage)
                <div class="mt-5 rounded-[1.8rem] border border-sky-400/18 bg-sky-500/10 p-5 text-sm leading-7 text-sky-50">
                    {{ $milestoneMessage }}
                </div>
            @endif

            @if ($trialEnded)
                <div class="mt-5 rounded-[1.8rem] border border-rose-400/18 bg-rose-500/10 p-5">
                    <p class="text-lg font-semibold text-rose-100">{{ __('site.trial.dashboard.ended_title') }}</p>
                    <p class="mt-3 text-sm leading-7 text-rose-50/90">{{ __('site.trial.dashboard.ended_copy') }}</p>
                    <form method="POST" action="{{ route('trial.retry') }}" class="mt-5">
                        @csrf
                        <button type="submit" class="primary-cta rounded-full px-8 py-4 text-base font-semibold">
                            {{ __('site.trial.dashboard.retry_button') }}
                        </button>
                    </form>
                </div>
            @endif

            <div class="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-5">
                @foreach ($metrics as $metric)
                    <article class="surface-panel rounded-[1.8rem] p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $metric['label'] }}</p>
                        <p class="mt-4 text-2xl font-semibold text-white">{{ $metric['value'] }}</p>
                    </article>
                @endforeach
            </div>

            <div class="mt-10 grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
                <section class="surface-panel rounded-[2rem] p-6">
                    <h2 class="text-xl font-semibold text-white">{{ __('site.trial.dashboard.restrictions_title') }}</h2>
                    <ul class="mt-5 space-y-3 text-sm text-slate-300">
                        @foreach (trans('site.trial.dashboard.restrictions') as $restriction)
                            <li class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">{{ $restriction }}</li>
                        @endforeach
                    </ul>
                </section>

                <section class="surface-panel rounded-[2rem] p-6">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div>
                            <h2 class="text-xl font-semibold text-white">{{ __('site.trial.dashboard.markets_title') }}</h2>
                            <ul class="mt-5 space-y-3 text-sm text-slate-300">
                                @foreach ($allowedSymbols as $symbol)
                                    <li class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">{{ $symbol }}</li>
                                @endforeach
                            </ul>
                        </div>

                        <div>
                            <h2 class="text-xl font-semibold text-white">{{ __('site.trial.dashboard.rules_title') }}</h2>
                            <dl class="mt-5 space-y-3 text-sm">
                                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                    <dt class="text-slate-400">{{ __('site.trial.dashboard.rule_labels.starting_balance') }}</dt>
                                    <dd class="font-semibold text-white">${{ number_format($startingBalance, 0) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                    <dt class="text-slate-400">{{ __('site.trial.dashboard.rule_labels.daily_limit') }}</dt>
                                    <dd class="font-semibold text-white">{{ $displayRules['daily_drawdown_limit'] ?? 0 }}%</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                    <dt class="text-slate-400">{{ __('site.trial.dashboard.rule_labels.max_limit') }}</dt>
                                    <dd class="font-semibold text-white">{{ $displayRules['max_drawdown_limit'] ?? 0 }}%</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                    <dt class="text-slate-400">{{ __('site.trial.dashboard.rule_labels.status') }}</dt>
                                    <dd class="font-semibold text-white">{{ $trialEnded ? __('site.trial.statuses.ended') : __('site.trial.statuses.active') }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </section>
@endsection
