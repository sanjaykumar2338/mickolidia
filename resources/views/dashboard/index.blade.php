@extends('layouts.dashboard')

@section('title', __('site.dashboard.preview_title').' | '.__('site.meta.brand'))
@section('dashboard-title', __('site.dashboard.preview_title'))
@section('dashboard-subtitle', __('site.dashboard.preview_subtitle'))

@section('content')
    @php
        $toneClasses = [
            'amber' => 'border-amber-400/18 bg-amber-400/10 text-amber-100',
            'emerald' => 'border-emerald-400/18 bg-emerald-500/10 text-emerald-100',
            'rose' => 'border-rose-400/18 bg-rose-500/10 text-rose-100',
            'sky' => 'border-sky-400/18 bg-sky-500/10 text-sky-100',
            'slate' => 'border-white/10 bg-white/5 text-slate-200',
        ];
        $linkedAccounts = collect($accounts)->skip(1)->values();
    @endphp

    <div class="space-y-6">
        <x-consistency-banner :title="$consistencyBanner['title']" :message="$consistencyBanner['message']" :meta="$consistencyBanner['meta']" />

        @if ($dashboardHero && $primaryAccount)
            @include('dashboard.partials.overview-hero', ['hero' => $dashboardHero, 'primaryAccount' => $primaryAccount])

            <div class="grid gap-6 2xl:grid-cols-[minmax(0,1.3fr)_minmax(20rem,0.94fr)]">
                @include('dashboard.partials.performance-chart', ['performanceChart' => $performanceChart])

                <div class="space-y-6">
                    <section class="surface-panel rounded-[2rem] p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">Risk & milestones</p>
                                <h3 class="mt-3 text-2xl font-semibold text-white">Progress toward the rules</h3>
                            </div>
                            <span class="rounded-full border border-white/10 bg-white/6 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-200">
                                {{ $primaryAccount['challenge_phase'] }}
                            </span>
                        </div>

                        <div class="mt-6 space-y-4">
                            @foreach ($progressTracks as $track)
                                <article class="rounded-[1.55rem] border border-white/8 bg-black/18 p-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-semibold text-white">{{ $track['label'] }}</p>
                                            <p class="mt-1 text-xs text-slate-400">{{ $track['current'] }} / {{ $track['target'] }}</p>
                                        </div>
                                        <span class="{{ $toneClasses[$track['tone']] ?? $toneClasses['slate'] }} rounded-full border px-3 py-1 text-xs font-semibold">
                                            {{ $track['value_label'] }}
                                        </span>
                                    </div>
                                    <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-white/8">
                                        <div class="h-full rounded-full {{ match ($track['tone']) {
                                            'emerald' => 'bg-emerald-400',
                                            'rose' => 'bg-rose-400',
                                            'sky' => 'bg-sky-400',
                                            default => 'bg-amber-400',
                                        } }}" style="width: {{ $track['value'] }}%"></div>
                                    </div>
                                    <p class="mt-3 text-sm leading-6 text-slate-400">{{ $track['meta'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    </section>

                    <section class="surface-panel rounded-[2rem] p-5 sm:p-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">History snapshot</p>
                        <h3 class="mt-3 text-2xl font-semibold text-white">{{ $analyticsSummary['title'] ?? 'History & analytics' }}</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-400">{{ $analyticsSummary['message'] ?? 'Trading analytics become available once synced.' }}</p>

                        @if (($analyticsSummary['is_available'] ?? false) && filled($analyticsSummary['cards'] ?? []))
                            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                @foreach ($analyticsSummary['cards'] as $card)
                                    <article class="rounded-[1.45rem] border border-white/8 bg-black/18 p-4">
                                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-400">{{ $card['label'] }}</p>
                                        <p class="mt-3 text-2xl font-semibold text-white">{{ $card['value'] }}</p>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-6 rounded-[1.55rem] border border-dashed border-white/12 bg-white/3 px-4 py-5 text-sm leading-7 text-slate-400">
                                Metrics like gross profit, expectancy, and win rate are shown only when the synced snapshot includes detailed closed-trade rows.
                            </div>
                        @endif
                    </section>

                    <section class="surface-panel rounded-[2rem] p-5 sm:p-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{{ __('site.dashboard.overview.payout_title') }}</p>
                        <h3 class="mt-3 text-2xl font-semibold text-white">Payout readiness</h3>
                        <div class="mt-6 grid gap-3 sm:grid-cols-2">
                            <article class="rounded-[1.45rem] border border-white/8 bg-black/18 p-4">
                                <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('site.dashboard.payouts.next_window') }}</p>
                                <p class="mt-3 text-xl font-semibold text-white">{{ $payoutSummary['next_window'] }}</p>
                            </article>
                            <article class="rounded-[1.45rem] border border-white/8 bg-black/18 p-4">
                                <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('site.dashboard.labels.eligible_profit') }}</p>
                                <p class="mt-3 text-xl font-semibold text-white">{{ $payoutSummary['eligible_profit'] }}</p>
                            </article>
                        </div>
                        <div class="mt-3 rounded-[1.45rem] border border-white/8 bg-black/18 p-4 text-sm leading-7 text-slate-300">
                            <p><span class="font-semibold text-white">Status:</span> {{ $payoutSummary['status'] }}</p>
                            <p class="mt-2 text-slate-400">{{ $payoutSummary['cycle_note'] }}</p>
                        </div>
                    </section>
                </div>
            </div>

            <section class="surface-panel rounded-[2rem] p-5 sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">Performance summary</p>
                        <h3 class="mt-3 text-2xl font-semibold text-white">Key account metrics</h3>
                    </div>
                    <p class="max-w-xl text-sm leading-7 text-slate-400">
                        Only metrics that are already available from the synced account or the latest trade payload are shown here.
                    </p>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($performanceCards as $card)
                        <article class="rounded-[1.65rem] border border-white/8 bg-black/18 p-4">
                            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-400">{{ $card['label'] }}</p>
                            <p class="mt-3 text-2xl font-semibold {{ match ($card['tone']) {
                                'emerald' => 'text-emerald-100',
                                'rose' => 'text-rose-100',
                                'sky' => 'text-sky-100',
                                'amber' => 'text-amber-100',
                                default => 'text-white',
                            } }}">
                                {{ $card['value'] }}
                            </p>
                            <p class="mt-2 text-sm leading-6 text-slate-400">{{ $card['hint'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            @include('dashboard.partials.trades-panel', ['tradesPanel' => $tradesPanel])

            @if ($linkedAccounts->isNotEmpty())
                <section class="surface-panel rounded-[2rem] p-5 sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">Linked accounts</p>
                            <h3 class="mt-3 text-2xl font-semibold text-white">Other synced challenges</h3>
                        </div>
                        <a href="{{ route('dashboard.accounts') }}" class="inline-flex rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                            {{ __('site.dashboard.nav.accounts') }}
                        </a>
                    </div>

                    <div class="mt-6 grid gap-4 xl:grid-cols-2">
                        @foreach ($linkedAccounts as $account)
                            <article class="rounded-[1.75rem] border border-white/8 bg-black/18 p-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-lg font-semibold text-white">{{ $account['plan'] }}</p>
                                        <p class="mt-1 truncate text-sm text-slate-400">{{ $account['reference'] }} • {{ $account['platform_account_id'] }}</p>
                                    </div>
                                    <span class="{{ $toneClasses[$account['floating_pnl_tone']] ?? $toneClasses['slate'] }} rounded-full border px-3 py-1 text-xs font-semibold">
                                        {{ $account['challenge_status'] }}
                                    </span>
                                </div>

                                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-[1.3rem] border border-white/6 bg-white/4 p-4">
                                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">Balance</p>
                                        <p class="mt-2 text-lg font-semibold text-white">{{ $account['balance'] }}</p>
                                    </div>
                                    <div class="rounded-[1.3rem] border border-white/6 bg-white/4 p-4">
                                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">Equity</p>
                                        <p class="mt-2 text-lg font-semibold text-white">{{ $account['equity'] }}</p>
                                    </div>
                                    <div class="rounded-[1.3rem] border border-white/6 bg-white/4 p-4">
                                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">Floating P&L</p>
                                        <p class="mt-2 text-lg font-semibold {{ match ($account['floating_pnl_tone']) {
                                            'emerald' => 'text-emerald-100',
                                            'rose' => 'text-rose-100',
                                            default => 'text-white',
                                        } }}">{{ $account['floating_pnl'] }}</p>
                                    </div>
                                </div>

                                <div class="mt-5">
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="text-slate-400">Target progress</span>
                                        <span class="font-semibold text-white">{{ $account['progress'] }}</span>
                                    </div>
                                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-white/8">
                                        <div class="h-full rounded-full bg-amber-400" style="width: {{ $account['progress_value'] }}%"></div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        @else
            <section class="surface-panel rounded-[2rem] p-6 sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{{ __('site.dashboard.preview_title') }}</p>
                <h2 class="mt-3 text-3xl font-semibold text-white">{{ $emptyState['title'] }}</h2>
                <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-400">{{ $emptyState['message'] }}</p>

                <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($summaryCards as $card)
                        <x-stat-card :label="$card['label']" :value="$card['value']" :hint="$card['hint']" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
