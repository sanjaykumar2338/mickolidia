@extends('admin.layout')

@section('title', $client['full_name'].' | '.__('site.admin.client_show.title'))

@section('content')
    @php
        $statusClass = match (strtolower($client['account_status_key'])) {
            'active', 'completed' => 'border-emerald-400/25 bg-emerald-500/12 text-emerald-100',
            'passed' => 'border-sky-400/25 bg-sky-500/12 text-sky-100',
            'failed', 'cancelled' => 'border-rose-400/25 bg-rose-500/12 text-rose-100',
            default => 'border-amber-400/25 bg-amber-400/12 text-amber-50',
        };
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <span class="section-label">{{ __('site.admin.client_show.eyebrow') }}</span>
            <h1 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ $client['full_name'] }}</h1>
            <p class="mt-4 max-w-3xl text-base leading-8 text-slate-300">{{ __('site.admin.client_show.description') }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            @if ($client['can_activate'])
                <form method="POST" action="{{ route('admin.clients.activate', $client['id']) }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-amber-400/25 bg-amber-400/10 px-4 py-2 text-sm font-semibold text-amber-100 transition hover:border-amber-300/40 hover:bg-amber-400/18">
                        {{ __('site.admin.table.activate_account') }}
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.clients.index') }}" class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                {{ __('site.admin.client_show.back') }}
            </a>
        </div>
    </div>

    @if (! empty($accountOptions))
        <section class="mt-8 surface-panel rounded-[2rem] p-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">{{ __('Trading accounts') }}</p>
                    <h2 class="mt-3 text-2xl font-semibold text-white">{{ __('Per-account review') }}</h2>
                </div>
                <p class="max-w-3xl text-sm leading-7 text-slate-400">
                    {{ __('Switch accounts here to inspect the synced metrics, trade history, and platform references for the exact trader account you want to review.') }}
                </p>
            </div>

            <div class="mt-6 grid gap-4 xl:grid-cols-2">
                @foreach ($accountOptions as $accountOption)
                    @php
                        $accountOptionStatusClass = match (strtolower($accountOption['status_key'])) {
                            'active', 'passed', 'completed', 'funded' => 'border-emerald-400/25 bg-emerald-500/12 text-emerald-100',
                            'failed', 'cancelled' => 'border-rose-400/25 bg-rose-500/12 text-rose-100',
                            default => 'border-amber-400/25 bg-amber-400/12 text-amber-50',
                        };
                    @endphp
                    <a
                        href="{{ $accountOption['url'] }}"
                        class="rounded-[1.6rem] border px-5 py-4 transition {{ $accountOption['is_selected'] ? 'border-amber-300/35 bg-amber-300/10 shadow-[0_18px_45px_rgba(2,6,23,0.24)]' : 'border-white/8 bg-black/18 hover:border-white/16 hover:bg-white/6' }}"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-lg font-semibold text-white">{{ $accountOption['reference'] }}</p>
                                <p class="mt-1 text-sm text-slate-400">{{ $accountOption['phase'] }} • {{ $accountOption['platform_login'] }}</p>
                            </div>
                            <span class="{{ $accountOptionStatusClass }} inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]">
                                {{ $accountOption['status'] }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="mt-8 grid gap-5 lg:grid-cols-[0.9fr_1.1fr]">
        <section class="surface-panel rounded-[2rem] p-6">
            <h2 class="text-lg font-semibold text-white">{{ __('site.admin.client_show.client_summary') }}</h2>
            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">{{ __('site.admin.table.email') }}</dt>
                    <dd class="font-semibold text-white">{{ $client['email'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">{{ __('site.admin.table.country') }}</dt>
                    <dd class="font-semibold text-white">{{ $client['country'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">{{ __('site.admin.table.plan_selected') }}</dt>
                    <dd class="font-semibold text-white">{{ $client['plan_selected'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">{{ __('site.admin.table.payment_amount') }}</dt>
                    <dd class="font-semibold text-white">{{ $client['payment_amount'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">{{ __('site.admin.table.payment_provider') }}</dt>
                    <dd class="font-semibold text-white">{{ $client['payment_provider'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">{{ __('site.admin.table.payment_status') }}</dt>
                    <dd class="font-semibold text-white">{{ $client['payment_status'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">{{ __('site.admin.table.order_date') }}</dt>
                    <dd class="font-semibold text-white">{{ $client['order_date'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">{{ __('site.admin.table.account_status') }}</dt>
                    <dd>
                        <span class="{{ $statusClass }} inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]">
                            {{ $client['account_status'] }}
                        </span>
                    </dd>
                </div>
            </dl>
        </section>

        <section class="surface-panel rounded-[2rem] p-6">
            <h2 class="text-lg font-semibold text-white">{{ __('site.admin.client_show.metrics_overview') }}</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($metrics as $metric)
                    <article class="surface-card rounded-[1.6rem] p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $metric['label'] }}</p>
                        <p class="mt-4 text-2xl font-semibold text-white">{{ $metric['value'] }}</p>
                    </article>
                @endforeach
            </div>

            <div class="mt-6 rounded-[1.8rem] border border-amber-400/18 bg-amber-400/10 p-5 text-sm leading-7 text-amber-50">
                Live account state now comes from the selected linked trading account, local rule evaluation, and sync history. Missing platform credentials or account linkage will appear below as sync gaps instead of fake metrics.
            </div>

            @if ($selectedAccount !== null)
                <div class="mt-6 surface-card rounded-[1.8rem] p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">{{ __('site.admin.client_show.account_snapshot') }}</p>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.admin.account.reference') }}</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $selectedAccount->account_reference ?? 'N/A' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.admin.account.platform') }}</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $selectedAccount->platform }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.admin.account.stage') }}</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $selectedAccount->stage }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Challenge Type</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $selectedAccount->challenge_type === 'one_step' ? '1-Step Instant' : '2-Step Pro' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Current Phase</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $selectedAccount->challenge_type === 'one_step' ? 'Single Phase' : ((int) $selectedAccount->phase_index > 1 ? 'Phase 2' : 'Phase 1') }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Challenge Status</dt>
                            <dd class="mt-2 font-semibold text-white">{{ str($selectedAccount->challenge_status ?: $selectedAccount->account_status)->replace('_', ' ')->title() }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.admin.account.balance') }}</dt>
                            <dd class="mt-2 font-semibold text-white">${{ number_format((float) $selectedAccount->balance, 2) }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Equity</dt>
                            <dd class="mt-2 font-semibold text-white">${{ number_format((float) $selectedAccount->equity, 2) }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Platform Account ID</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $selectedAccount->platform_account_id ?? 'Link pending' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Platform Login</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $selectedAccount->platform_login ?? 'Link pending' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Environment</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $selectedAccount->platform_environment ?? 'N/A' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Last Synced</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $providerReferences['last_synced_at'] }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Daily Drawdown</dt>
                            <dd class="mt-2 font-semibold text-white">${{ number_format((float) $selectedAccount->daily_drawdown, 2) }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Daily Loss Used / Remaining</dt>
                            <dd class="mt-2 font-semibold text-white">
                                ${{ number_format((float) $selectedAccount->daily_loss_used, 2) }}
                                /
                                ${{ number_format(max((float) $selectedAccount->daily_drawdown_limit_amount - (float) $selectedAccount->daily_loss_used, 0), 2) }}
                            </dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Total Drawdown</dt>
                            <dd class="mt-2 font-semibold text-white">${{ number_format((float) $selectedAccount->max_drawdown, 2) }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Max Drawdown Used / Remaining</dt>
                            <dd class="mt-2 font-semibold text-white">
                                ${{ number_format((float) $selectedAccount->max_drawdown_used, 2) }}
                                /
                                ${{ number_format(max((float) $selectedAccount->max_drawdown_limit_amount - (float) $selectedAccount->max_drawdown_used, 0), 2) }}
                            </dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Profit Target Progress</dt>
                            <dd class="mt-2 font-semibold text-white">{{ number_format((float) $selectedAccount->profit_target_progress_percent, 1) }}%</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Failure Reason</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $selectedAccount->failure_reason ? str($selectedAccount->failure_reason)->replace('_', ' ')->title() : 'None' }}</dd>
                        </div>
                    </dl>

                    @if ($providerReferences['sync_error'] !== 'None')
                        <div class="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm leading-7 text-rose-100">
                            {{ $providerReferences['sync_error'] }}
                        </div>
                    @endif
                </div>
            @endif
        </section>
    </div>

    @if ($selectedAccount !== null && $selectedAccount->platform_slug === 'mt5')
        @php
            $storedServerName = data_get($selectedAccount->meta ?? [], 'credentials.server')
                ?? data_get($selectedAccount->meta ?? [], 'mt5_server');
            $hasStoredPassword = filled(data_get($selectedAccount->meta ?? [], 'credentials.password'))
                || filled(data_get($selectedAccount->meta ?? [], 'credentials.trading_password'))
                || filled(data_get($selectedAccount->meta ?? [], 'trading_password'))
                || filled(data_get($selectedAccount->meta ?? [], 'mt5_password'));
        @endphp

        <section class="mt-8 surface-panel rounded-[2rem] p-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">{{ __('MT5 delivery') }}</p>
                    <h2 class="mt-3 text-2xl font-semibold text-white">{{ __('Credential handoff') }}</h2>
                </div>
                <p class="max-w-3xl text-sm leading-7 text-slate-400">
                    {{ __('Use this panel after the broker-side MT5 account is ready. Saving the login, server, and trading password here lets Wolforix send the purchase credential email once and prevents duplicate sends later.') }}
                </p>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-[0.9fr_1.1fr]">
                <div class="surface-card rounded-[1.8rem] p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Current handoff state') }}</p>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('Client email') }}</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $client['email'] }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('MT5 login') }}</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $selectedAccount->platform_login ?: ($selectedAccount->platform_account_id ?: 'Not saved yet') }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('Server name') }}</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $storedServerName ?: 'Not saved yet' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('Trading password') }}</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $hasStoredPassword ? 'Stored securely for delivery' : 'Not saved yet' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('Credential email') }}</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $selectedAccount->challenge_purchase_email_sent_at?->format('Y-m-d H:i') ?: 'Not sent yet' }}</dd>
                        </div>
                    </dl>
                </div>

                <form method="POST" action="{{ route('admin.clients.credentials', $client['id']) }}" class="surface-card rounded-[1.8rem] p-5">
                    @csrf
                    <input type="hidden" name="account_id" value="{{ $selectedAccount->id }}">

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('MT5 login') }}</span>
                            <input
                                type="text"
                                name="platform_login"
                                value="{{ old('platform_login', $selectedAccount->platform_login ?? $selectedAccount->platform_account_id) }}"
                                class="mt-2 w-full rounded-lg border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none transition focus:border-amber-300/40 focus:ring-0"
                                placeholder="105381073"
                            >
                        </label>

                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Platform account ID') }}</span>
                            <input
                                type="text"
                                name="platform_account_id"
                                value="{{ old('platform_account_id', $selectedAccount->platform_account_id ?? $selectedAccount->platform_login) }}"
                                class="mt-2 w-full rounded-lg border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none transition focus:border-amber-300/40 focus:ring-0"
                                placeholder="105381073"
                            >
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Server name') }}</span>
                            <input
                                type="text"
                                name="server_name"
                                value="{{ old('server_name', $storedServerName) }}"
                                class="mt-2 w-full rounded-lg border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none transition focus:border-amber-300/40 focus:ring-0"
                                placeholder="Wolforix-Demo"
                            >
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Trading password') }}</span>
                            <input
                                type="password"
                                name="trading_password"
                                value=""
                                class="mt-2 w-full rounded-lg border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none transition focus:border-amber-300/40 focus:ring-0"
                                placeholder="{{ $hasStoredPassword ? 'Leave blank to keep the current password' : 'Enter the MT5 trading password' }}"
                            >
                            <p class="mt-2 text-xs leading-6 text-slate-400">{{ __('Leave this blank to keep the existing stored password.') }}</p>
                        </label>
                    </div>

                    @if ($errors->any())
                        <div class="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                            {{ __('Please review the credential fields and try again.') }}
                        </div>
                    @endif

                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm leading-7 text-slate-400">
                            {{ __('The purchase credential email only sends after all three values are present: MT5 login, server, and trading password.') }}
                        </p>
                        <button type="submit" class="rounded-full border border-amber-400/25 bg-amber-400/10 px-4 py-2 text-sm font-semibold text-amber-100 transition hover:border-amber-300/40 hover:bg-amber-400/18">
                            {{ __('Save MT5 credentials') }}
                        </button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    <div class="mt-8">
        @include('dashboard.partials.trades-panel', [
            'tradesPanel' => $tradesPanel,
            'panelEyebrow' => __('Trade review'),
            'panelTitle' => __('Detailed trade history'),
            'panelDescription' => __('Review the same persisted open and closed trade rows the client sees, with lifecycle timestamps, direction, pricing, and result context for the selected account.'),
        ])
    </div>

    <div class="mt-8 grid gap-5 lg:grid-cols-2">
        <section class="surface-panel rounded-[2rem] p-6">
            <h2 class="text-lg font-semibold text-white">{{ __('site.admin.client_show.billing_summary') }}</h2>
            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">{{ __('site.checkout.full_name') }}</dt>
                    <dd class="font-semibold text-white">{{ $billing['full_name'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">{{ __('site.checkout.street_address') }}</dt>
                    <dd class="font-semibold text-white">{{ $billing['street_address'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">{{ __('site.checkout.city') }}</dt>
                    <dd class="font-semibold text-white">{{ $billing['city'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">{{ __('site.checkout.postal_code') }}</dt>
                    <dd class="font-semibold text-white">{{ $billing['postal_code'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">{{ __('site.checkout.country') }}</dt>
                    <dd class="font-semibold text-white">{{ $billing['country'] }}</dd>
                </div>
            </dl>
        </section>

        <section class="surface-panel rounded-[2rem] p-6">
            <h2 class="text-lg font-semibold text-white">{{ __('site.admin.client_show.provider_references') }}</h2>
            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">{{ __('site.checkout.success.order_number') }}</dt>
                    <dd class="font-semibold text-white">{{ $providerReferences['order_number'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">Checkout ID</dt>
                    <dd class="font-semibold text-white">{{ $providerReferences['checkout_id'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">Payment ID</dt>
                    <dd class="font-semibold text-white">{{ $providerReferences['payment_id'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">Customer ID</dt>
                    <dd class="font-semibold text-white">{{ $providerReferences['customer_id'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">Platform Account ID</dt>
                    <dd class="font-semibold text-white">{{ $providerReferences['platform_account_id'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">Platform Login</dt>
                    <dd class="font-semibold text-white">{{ $providerReferences['platform_login'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">Environment</dt>
                    <dd class="font-semibold text-white">{{ $providerReferences['platform_environment'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">Last Synced</dt>
                    <dd class="font-semibold text-white">{{ $providerReferences['last_synced_at'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">Last Evaluated</dt>
                    <dd class="font-semibold text-white">{{ $providerReferences['last_evaluated_at'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">Sync Source</dt>
                    <dd class="font-semibold text-white">{{ $providerReferences['sync_source'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">Authorized Accounts</dt>
                    <dd class="font-semibold text-white">{{ $providerReferences['authorized_accounts_count'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/3 px-4 py-3">
                    <dt class="text-slate-400">Last Authorized</dt>
                    <dd class="font-semibold text-white">{{ $providerReferences['last_authorized_at'] }}</dd>
                </div>
            </dl>
        </section>
    </div>
@endsection
