@extends('admin.layout')

@section('title', $client['full_name'].' | '.__('site.admin.client_show.title'))

@section('content')
    @php
        $statusClass = match (strtolower($client['account_status'])) {
            'completed' => 'border-emerald-400/25 bg-emerald-500/12 text-emerald-100',
            'cancelled' => 'border-rose-400/25 bg-rose-500/12 text-rose-100',
            default => 'border-amber-400/25 bg-amber-400/12 text-amber-50',
        };
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <span class="section-label">{{ __('site.admin.client_show.eyebrow') }}</span>
            <h1 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ $client['full_name'] }}</h1>
            <p class="mt-4 max-w-3xl text-base leading-8 text-slate-300">{{ __('site.admin.client_show.description') }}</p>
        </div>
        <a href="{{ route('admin.clients.index') }}" class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
            {{ __('site.admin.client_show.back') }}
        </a>
    </div>

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
                Live account state now comes from the latest linked trading account, local rule evaluation, and sync history. Missing platform credentials or account linkage will appear below as sync gaps instead of fake metrics.
            </div>

            @if ($latestAccount !== null)
                <div class="mt-6 surface-card rounded-[1.8rem] p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">{{ __('site.admin.client_show.account_snapshot') }}</p>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.admin.account.reference') }}</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $latestAccount->account_reference ?? 'N/A' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.admin.account.platform') }}</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $latestAccount->platform }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.admin.account.stage') }}</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $latestAccount->stage }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Challenge Type</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $latestAccount->challenge_type === 'one_step' ? '1-Step Instant' : '2-Step Pro' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Current Phase</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $latestAccount->challenge_type === 'one_step' ? 'Single Phase' : ((int) $latestAccount->phase_index > 1 ? 'Phase 2' : 'Phase 1') }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Challenge Status</dt>
                            <dd class="mt-2 font-semibold text-white">{{ str($latestAccount->challenge_status ?: $latestAccount->account_status)->replace('_', ' ')->title() }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">{{ __('site.admin.account.balance') }}</dt>
                            <dd class="mt-2 font-semibold text-white">${{ number_format((float) $latestAccount->balance, 2) }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Equity</dt>
                            <dd class="mt-2 font-semibold text-white">${{ number_format((float) $latestAccount->equity, 2) }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Platform Account ID</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $latestAccount->platform_account_id ?? 'Link pending' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Platform Login</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $latestAccount->platform_login ?? 'Link pending' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Environment</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $latestAccount->platform_environment ?? 'N/A' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Last Synced</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $providerReferences['last_synced_at'] }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Daily Drawdown</dt>
                            <dd class="mt-2 font-semibold text-white">${{ number_format((float) $latestAccount->daily_drawdown, 2) }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Daily Loss Used / Remaining</dt>
                            <dd class="mt-2 font-semibold text-white">
                                ${{ number_format((float) $latestAccount->daily_loss_used, 2) }}
                                /
                                ${{ number_format(max((float) $latestAccount->daily_drawdown_limit_amount - (float) $latestAccount->daily_loss_used, 0), 2) }}
                            </dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Total Drawdown</dt>
                            <dd class="mt-2 font-semibold text-white">${{ number_format((float) $latestAccount->max_drawdown, 2) }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Max Drawdown Used / Remaining</dt>
                            <dd class="mt-2 font-semibold text-white">
                                ${{ number_format((float) $latestAccount->max_drawdown_used, 2) }}
                                /
                                ${{ number_format(max((float) $latestAccount->max_drawdown_limit_amount - (float) $latestAccount->max_drawdown_used, 0), 2) }}
                            </dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Profit Target Progress</dt>
                            <dd class="mt-2 font-semibold text-white">{{ number_format((float) $latestAccount->profit_target_progress_percent, 1) }}%</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                            <dt class="text-slate-400">Failure Reason</dt>
                            <dd class="mt-2 font-semibold text-white">{{ $latestAccount->failure_reason ? str($latestAccount->failure_reason)->replace('_', ' ')->title() : 'None' }}</dd>
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
