@extends('layouts.dashboard')

@section('title', __('site.dashboard.accounts_page.title').' | '.__('site.meta.brand'))
@section('dashboard-title', __('site.dashboard.accounts_page.title'))
@section('dashboard-subtitle', __('site.dashboard.accounts_page.subtitle'))

@section('content')
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[1.8rem] border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm leading-7 text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-[1.8rem] border border-rose-400/20 bg-rose-500/10 px-5 py-4 text-sm leading-7 text-rose-100">
                {{ session('error') }}
            </div>
        @endif

        @if (($primaryAccount['platform_slug'] ?? null) === 'mt5')
            @php
                $primarySyncToneClasses = match ($primaryAccount['sync_freshness_tone'] ?? 'slate') {
                    'emerald' => 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100',
                    'amber' => 'border-amber-400/20 bg-amber-500/10 text-amber-100',
                    'rose' => 'border-rose-400/20 bg-rose-500/10 text-rose-100',
                    default => 'border-white/10 bg-white/5 text-slate-200',
                };
            @endphp
            <div class="surface-panel rounded-[2rem] p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">MT5 live sync</p>
                        <h2 class="mt-3 text-2xl font-semibold text-white">Near real-time challenge metrics</h2>
                        <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-400">
                            Open trades, floating P&amp;L, balance changes, and challenge rule usage refresh from MT5 trade events with a timer fallback if an event-triggered update is missed.
                        </p>
                    </div>
                    <span class="rounded-full border px-4 py-2 text-sm font-semibold {{ $primarySyncToneClasses }}">
                        {{ $primaryAccount['sync_freshness'] }}
                    </span>
                </div>

                <dl class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="surface-card rounded-[1.6rem] p-5">
                        <dt class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Sync status</dt>
                        <dd class="mt-3 text-lg font-semibold text-white">{{ $primaryAccount['sync_status'] }}</dd>
                    </div>
                    <div class="surface-card rounded-[1.6rem] p-5">
                        <dt class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Last synced</dt>
                        <dd class="mt-3 text-lg font-semibold text-white">{{ $primaryAccount['last_synced_at'] }}</dd>
                    </div>
                    <div class="surface-card rounded-[1.6rem] p-5">
                        <dt class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Sync freshness</dt>
                        <dd class="mt-3 text-lg font-semibold text-white">{{ $primaryAccount['sync_freshness'] }}</dd>
                        <p class="mt-2 text-sm leading-6 text-slate-400">{{ $primaryAccount['sync_freshness_hint'] }}</p>
                    </div>
                    <div class="surface-card rounded-[1.6rem] p-5">
                        <dt class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Data source</dt>
                        <dd class="mt-3 text-lg font-semibold text-white">{{ $primaryAccount['sync_source'] }}</dd>
                    </div>
                </dl>

                @if ($primaryAccount['sync_error'])
                    <div class="mt-4 rounded-[1.5rem] border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm leading-7 text-rose-100">
                        {{ $primaryAccount['sync_error'] }}
                    </div>
                @endif
            </div>
        @else
            <div class="surface-panel rounded-[2rem] p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">cTrader connection</p>
                        <h2 class="mt-3 text-2xl font-semibold text-white">
                            {{ $ctraderConnection['is_connected'] ? 'Connected and ready to sync' : 'Connect your cTrader account' }}
                        </h2>
                        <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-400">
                            {{ $ctraderConnection['is_connected']
                                ? 'Wolforix can now read the authorized '.$ctraderConnection['broker_name'].' cTrader accounts linked to your cTID and sync challenge metrics into the dashboard.'
                                : 'Authorize Wolforix with '.$ctraderConnection['broker_name'].' cTrader to link your challenge account, fetch live balance/equity data, and keep rule monitoring up to date.' }}
                        </p>
                    </div>
                    <a href="{{ $ctraderConnection['connect_url'] }}" class="rounded-full border border-amber-400/30 bg-amber-400/12 px-5 py-3 text-sm font-semibold text-amber-50 transition hover:border-amber-300/40 hover:bg-amber-400/18">
                        {{ $ctraderConnection['is_connected'] ? 'Reconnect cTrader' : 'Connect cTrader' }}
                    </a>
                </div>

                <dl class="mt-6 grid gap-4 md:grid-cols-3">
                    <div class="surface-card rounded-[1.6rem] p-5">
                        <dt class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Broker</dt>
                        <dd class="mt-3 text-lg font-semibold text-white">{{ $ctraderConnection['broker_name'] }}</dd>
                    </div>
                    <div class="surface-card rounded-[1.6rem] p-5">
                        <dt class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Authorized accounts</dt>
                        <dd class="mt-3 text-lg font-semibold text-white">{{ $ctraderConnection['authorized_accounts_count'] }}</dd>
                    </div>
                    <div class="surface-card rounded-[1.6rem] p-5 md:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Last authorized</dt>
                        <dd class="mt-3 text-lg font-semibold text-white">{{ $ctraderConnection['last_authorized_at'] }}</dd>
                    </div>
                    <div class="surface-card rounded-[1.6rem] p-5 md:col-span-3">
                        <dt class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Account list sync</dt>
                        <dd class="mt-3 text-lg font-semibold text-white">{{ $ctraderConnection['last_synced_accounts_at'] }}</dd>
                    </div>
                </dl>

                @if ($ctraderConnection['last_error'])
                    <div class="mt-4 rounded-[1.5rem] border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm leading-7 text-rose-100">
                        {{ $ctraderConnection['last_error'] }}
                    </div>
                @endif

                @if (! empty($ctraderConnection['authorized_accounts']))
                    <div class="mt-4 rounded-[1.5rem] border border-white/8 bg-black/15 px-4 py-4 text-sm text-slate-300">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Authorized cTrader accounts</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($ctraderConnection['authorized_accounts'] as $authorizedAccount)
                                <span class="rounded-full border border-white/8 bg-white/4 px-3 py-1.5 text-xs font-semibold text-white">
                                    {{ $authorizedAccount['label'] }} • {{ $authorizedAccount['broker'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

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
                                <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Trading account</dt>
                                    <dd class="mt-2 font-semibold text-white">{{ $purchase['account_reference'] }}</dd>
                                </div>
                                <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Sync status</dt>
                                    <dd class="mt-2 font-semibold text-white">{{ $purchase['sync_status'] }}</dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($hasTradingAccounts)
            <div class="grid gap-5 lg:grid-cols-2">
                @foreach ($accounts as $account)
                    <article class="surface-panel rounded-[2rem] p-6">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold tracking-[0.24em] text-amber-300">{{ $account['plan'] }}</p>
                                <h2 class="mt-2 text-2xl font-semibold text-white">{{ $account['reference'] }}</h2>
                                <p class="mt-2 text-sm text-slate-400">{{ $account['challenge_type'] }} • {{ $account['challenge_phase'] }}</p>
                            </div>
                            <span class="rounded-full border border-white/8 bg-white/4 px-4 py-2 text-sm text-slate-200">{{ $account['status'] }}</span>
                        </div>

                        @php
                            $floatingToneClasses = match ($account['floating_pnl_tone'] ?? 'slate') {
                                'emerald' => 'text-emerald-100',
                                'rose' => 'text-rose-100',
                                default => 'text-white',
                            };
                            $syncToneClasses = match ($account['sync_freshness_tone'] ?? 'slate') {
                                'emerald' => 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100',
                                'amber' => 'border-amber-400/20 bg-amber-500/10 text-amber-100',
                                'rose' => 'border-rose-400/20 bg-rose-500/10 text-rose-100',
                                default => 'border-white/10 bg-white/5 text-slate-200',
                            };
                        @endphp

                        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <div class="surface-card rounded-3xl p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('site.dashboard.cards.balance') }}</p>
                                <p class="mt-3 text-2xl font-semibold text-white">{{ $account['balance'] }}</p>
                            </div>
                            <div class="surface-card rounded-3xl p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Equity</p>
                                <p class="mt-3 text-2xl font-semibold text-white">{{ $account['equity'] }}</p>
                            </div>
                            <div class="surface-card rounded-3xl p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Floating P&amp;L</p>
                                <p class="mt-3 text-2xl font-semibold {{ $floatingToneClasses }}">{{ $account['floating_pnl'] }}</p>
                            </div>
                            <div class="surface-card rounded-3xl p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Challenge phase</p>
                                <p class="mt-3 text-2xl font-semibold text-white">{{ $account['challenge_phase'] }}</p>
                            </div>
                            <div class="surface-card rounded-3xl p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Trading days</p>
                                <p class="mt-3 text-2xl font-semibold text-white">{{ $account['trading_days'] }}</p>
                            </div>
                            <div class="surface-card rounded-3xl p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Max drawdown used</p>
                                <p class="mt-3 text-2xl font-semibold text-white">{{ $account['max_drawdown_used'] }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
                            <div class="surface-card rounded-3xl p-5">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Challenge progress</p>
                                        <p class="mt-2 text-lg font-semibold text-white">{{ $account['challenge_status'] }}</p>
                                    </div>
                                    <span class="rounded-full border px-3 py-1.5 text-xs font-semibold {{ $syncToneClasses }}">
                                        {{ $account['sync_freshness'] }}
                                    </span>
                                </div>

                                <div class="mt-5">
                                    <div class="flex items-center justify-between gap-3 text-sm text-slate-300">
                                        <span>Profit target progress</span>
                                        <span class="font-semibold text-white">{{ $account['progress'] }}</span>
                                    </div>
                                    <div class="mt-3 h-2 rounded-full bg-white/6">
                                        <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-sky-400" style="width: {{ $account['progress_value'] }}%"></div>
                                    </div>
                                </div>

                                <dl class="mt-5 space-y-3 text-sm">
                                    <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                        <dt class="text-slate-400">Daily loss used</dt>
                                        <dd class="font-semibold text-white">{{ $account['daily_loss_used'] }} / {{ $account['daily_loss_limit'] }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                        <dt class="text-slate-400">Daily loss remaining</dt>
                                        <dd class="font-semibold text-white">{{ $account['daily_loss_remaining'] }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                        <dt class="text-slate-400">Max drawdown used</dt>
                                        <dd class="font-semibold text-white">{{ $account['max_drawdown_used'] }} / {{ $account['max_drawdown_limit'] }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                        <dt class="text-slate-400">Max drawdown remaining</dt>
                                        <dd class="font-semibold text-white">{{ $account['max_drawdown_remaining'] }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="surface-card rounded-3xl p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Sync details</p>
                                <dl class="mt-5 space-y-3 text-sm">
                                    <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                        <dt class="text-slate-400">Sync status</dt>
                                        <dd class="font-semibold text-white">{{ $account['sync_status'] }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                        <dt class="text-slate-400">Last synced</dt>
                                        <dd class="font-semibold text-white">{{ $account['last_synced_at'] }}</dd>
                                    </div>
                                    <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                        <dt class="text-slate-400">Sync freshness</dt>
                                        <dd class="mt-2 font-semibold text-white">{{ $account['sync_freshness'] }}</dd>
                                        <p class="mt-2 leading-6 text-slate-400">{{ $account['sync_freshness_hint'] }}</p>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                        <dt class="text-slate-400">Source</dt>
                                        <dd class="font-semibold text-white">{{ $account['sync_source'] }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                        <dt class="text-slate-400">Platform account</dt>
                                        <dd class="font-semibold text-white">{{ $account['platform_account_id'] }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                        <dt class="text-slate-400">Environment</dt>
                                        <dd class="font-semibold text-white">{{ $account['platform_environment'] }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                        <dt class="text-slate-400">Connection</dt>
                                        <dd class="font-semibold text-white">{{ $account['platform_status'] }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                                        <dt class="text-slate-400">Last evaluated</dt>
                                        <dd class="font-semibold text-white">{{ $account['last_evaluated_at'] }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        @if ($account['failure_reason'])
                            <div class="mt-4 surface-card rounded-3xl border border-rose-400/20 bg-rose-500/10 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-rose-100">Failure reason</p>
                                <p class="mt-3 text-lg font-semibold text-white">{{ $account['failure_reason'] }}</p>
                            </div>
                        @endif

                        @if ($account['needs_linking'] && ! empty($ctraderConnection['authorized_accounts']))
                            <div class="mt-4 surface-card rounded-3xl p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Link authorized cTrader account</p>
                                <div class="mt-4">
                                    <form method="POST" action="{{ $ctraderConnection['link_url'] }}" class="flex flex-col gap-3 sm:flex-row">
                                        @csrf
                                        <input type="hidden" name="trading_account_id" value="{{ $account['id'] }}">
                                        <select name="platform_account_id" class="min-w-0 flex-1 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white focus:border-amber-300/40 focus:outline-none">
                                            @foreach ($ctraderConnection['authorized_accounts'] as $authorizedAccount)
                                                <option value="{{ $authorizedAccount['id'] }}">
                                                    {{ $authorizedAccount['label'] }} • {{ $authorizedAccount['broker'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="rounded-full border border-amber-400/30 bg-amber-400/12 px-5 py-3 text-sm font-semibold text-amber-50 transition hover:border-amber-300/40 hover:bg-amber-400/18">
                                            Link account
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @else
            <div class="surface-panel rounded-[2rem] p-6">
                <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.dashboard.accounts_page.title') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-white">{{ $emptyState['title'] }}</h2>
                <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-400">{{ $emptyState['message'] }}</p>
            </div>
        @endif

        <div class="surface-card rounded-[2rem] p-6">
            <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.home.plans.eyebrow') }}</p>
            <div class="mt-6 grid gap-4 xl:grid-cols-4 md:grid-cols-2">
                @foreach ($availablePlans as $plan)
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
