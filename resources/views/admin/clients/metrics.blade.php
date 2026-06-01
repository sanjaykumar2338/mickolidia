@extends('admin.layout')

@section('title', 'Trader Metrics | '.__('site.meta.brand'))

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <span class="section-label">Admin Metrics</span>
            <h1 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ $client['full_name'] }}</h1>
            <p class="mt-4 max-w-3xl text-base leading-8 text-slate-300">{{ $client['email'] }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            @if (! empty($account['can_refresh_metaapi']) && ! empty($account['refresh_action_url']))
                <form method="POST" action="{{ $account['refresh_action_url'] }}">
                    @csrf
                    <input type="hidden" name="account" value="{{ request('account') }}">
                    <button type="submit" class="rounded-full border border-sky-300/30 bg-sky-400/12 px-4 py-2 text-sm font-semibold text-sky-100 transition hover:border-sky-200/50 hover:bg-sky-400/18">
                        Refresh
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.clients.index') }}" class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                Back to clients
            </a>
        </div>
    </div>

    @if (count($accountOptions) > 1)
        <div class="mt-8 flex flex-wrap gap-3">
            @foreach ($accountOptions as $option)
                <a href="{{ $option['url'] }}" class="{{ $option['is_selected'] ? 'border-amber-300/40 bg-amber-400/14 text-amber-100' : 'border-white/10 text-slate-300 hover:border-white/20 hover:bg-white/6' }} rounded-full border px-4 py-2 text-sm font-semibold transition">
                    {{ $option['reference'] }}
                </a>
            @endforeach
        </div>
    @endif

    <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            'Account reference' => $account['reference'],
            'Plan / challenge' => $account['plan'].' / '.$account['phase'],
            'Account status' => $account['status'].' / '.$account['challenge_status'],
            'Last sync' => $account['last_ea_sync'],
            'Challenge balance' => $account['balance'],
            'Challenge equity' => $account['equity'],
            'Current floating PnL' => $account['floating_pl'],
            'Snapshot P/L' => $account['snapshot_pl'],
            $account['today_profit_label'] ?? 'Today Closed P/L' => $account['today_profit'],
            'Total realized P/L' => $account['total_realized_pl'],
            'Last refreshed at' => $account['last_refreshed_at'],
            'Profit target progress' => $account['profit_target_progress'],
            'Trading days' => $account['trading_days'],
            'Open positions' => $account['open_positions_count'],
            'Closed trades' => $account['closed_trades_count'],
            'Lifecycle state' => $account['lifecycle_state'],
            'Sync health' => $account['sync_health'],
            'Onboarding' => $account['onboarding_state'],
            'Ready to trade' => $account['ready_to_trade'],
            'Phase 1 ready' => $account['phase_1_ready'],
            'Phase 2 ready' => $account['phase_2_ready'],
            'Provisioning Status' => $account['provisioning_status'],
            'Assigned MT5 Login' => $account['assigned_mt5_login'],
            'Pool Source' => $account['pool_source'],
            'MetaApi Connected' => $account['metaapi_connected'],
            'Dashboard source' => $account['sync_source'],
        ] as $label => $value)
            <div class="surface-panel rounded-[1.4rem] p-5">
                <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $label }}</dt>
                <dd class="mt-3 text-lg font-semibold text-white">{{ $value }}</dd>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="surface-panel rounded-[1.4rem] p-5">
            <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Sync status</dt>
            <dd class="mt-3">
                <span class="{{ $account['connector_badge'] }} inline-flex rounded-full border px-3 py-1 text-sm font-semibold">
                    {{ $account['connector_status'] }}
                </span>
            </dd>
        </div>
        <div class="surface-panel rounded-[1.4rem] p-5">
            <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Rule breach status</dt>
            <dd class="mt-3 text-lg font-semibold text-white">{{ $account['breach_status'] }}</dd>
        </div>
    </div>

    @if (! empty($metaApiRefresh['attempted']))
        <div class="{{ ! empty($metaApiRefresh['ok']) ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100' : 'border-amber-400/20 bg-amber-500/10 text-amber-100' }} mt-4 rounded-2xl border px-5 py-4 text-sm font-semibold">
            {{ $metaApiRefresh['message'] }}
        </div>
    @endif

    @if ($account['connector_is_stale'])
        <div class="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-5 py-4 text-sm font-semibold text-rose-100">
            MT5 data may be outdated because the account has not synced recently.
        </div>
    @endif

    <section class="mt-8 surface-panel rounded-[2rem] p-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-300">Calculation Breakdown</p>
            <h2 class="mt-3 text-2xl font-semibold text-white">Challenge progress and rule checks</h2>
        </div>
        <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($calculation['cards'] as $item)
                <div class="rounded-2xl border border-white/6 bg-black/15 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $item['label'] }}</dt>
                    <dd class="mt-2 break-words text-sm font-semibold text-white">{{ $item['value'] }}</dd>
                </div>
            @endforeach
        </div>
        <div class="mt-5 grid gap-3 lg:grid-cols-2">
            @foreach ($calculation['formulas'] as $label => $formula)
                <div class="rounded-2xl border border-white/6 bg-black/15 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $label }}</dt>
                    <dd class="mt-2 font-mono text-xs leading-5 text-slate-300">{{ $formula }}</dd>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-8 surface-panel rounded-[2rem] p-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-300">Today Summary</p>
                <h2 class="mt-3 text-2xl font-semibold text-white">Client trading activity today</h2>
            </div>
            <p class="text-sm text-slate-300">Source: {{ $todaySummary['today_profit_source'] }}</p>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                $todaySummary['today_profit_label'] => $todaySummary['today_profit_loss'],
                'Gross today profit' => $todaySummary['gross_today_profit'],
                'Today commission' => $todaySummary['today_commission'],
                'Today swap' => $todaySummary['today_swap'],
                'Net today profit' => $todaySummary['net_today_profit'],
                'Today closed trades count' => $todaySummary['today_closed_trades_count'],
                'Today open trades count' => $todaySummary['today_open_trades_count'],
                'Current floating PnL' => $todaySummary['current_floating_pnl'],
                'Last synced at' => $todaySummary['last_synced_at'],
            ] as $label => $value)
                <div class="rounded-2xl border border-white/6 bg-black/15 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $label }}</dt>
                    <dd class="mt-2 text-lg font-semibold text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-8 surface-panel rounded-[2rem] p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-300">Trade History</p>
                <h2 class="mt-3 text-2xl font-semibold text-white">Open and closed MT5 trades</h2>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-300">{{ $tradesMessage }}</p>
            </div>
            <div class="text-sm text-slate-300">
                Open {{ $tradesSummary['open'] ?? 0 }} · Closed {{ $tradesSummary['closed'] ?? 0 }}
            </div>
        </div>

        <form method="GET" action="{{ $tradeFilterActionUrl ?? route('admin.clients.metrics', $client['id']) }}" class="mt-6 grid gap-3 md:grid-cols-6">
            @foreach ($accountOptions as $option)
                @if ($option['is_selected'])
                    <input type="hidden" name="account" value="{{ $option['id'] }}">
                @endif
            @endforeach
            <select name="trade_status" class="rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white">
                <option value="both" @selected($filters['status'] === 'both')>All trades</option>
                <option value="open" @selected($filters['status'] === 'open')>Open trades</option>
                <option value="closed" @selected($filters['status'] === 'closed')>Closed trades</option>
            </select>
            <select name="symbol" class="rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white">
                <option value="">All symbols</option>
                @foreach ($symbols as $symbol)
                    <option value="{{ $symbol }}" @selected($filters['symbol'] === $symbol)>{{ $symbol }}</option>
                @endforeach
            </select>
            <select name="date_filter" class="rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white">
                <option value="" @selected($filters['date_filter'] === '')>Any date</option>
                <option value="today" @selected($filters['date_filter'] === 'today')>Today</option>
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white">
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white">
            <button type="submit" class="rounded-2xl border border-amber-400/25 bg-amber-400/10 px-4 py-3 text-sm font-semibold text-amber-100 transition hover:border-amber-300/40 hover:bg-amber-400/18">
                Apply filters
            </button>
        </form>

        <div class="mt-6 space-y-3 md:hidden">
            @forelse ($tradeRows as $row)
                <article class="rounded-[1.35rem] border border-white/6 bg-black/15 p-4 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-white">{{ $row['symbol'] }}</p>
                            <p class="mt-1 break-words text-xs uppercase tracking-[0.14em] text-slate-500">{{ $row['id'] }}</p>
                        </div>
                        <span class="shrink-0 rounded-full border border-white/10 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-slate-200">
                            {{ ($row['auto_closed_by_breach'] ?? false) ? 'auto closed' : strtolower((string) ($row['status'] ?? ($row['filter'] === 'open' ? 'open' : 'closed'))) }}
                        </span>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3">
                        @foreach ([
                            'Type' => $row['side'] === '—' ? '—' : strtolower((string) $row['side']),
                            'Lot size' => $row['volume'],
                            'Open time' => $row['open_date'],
                            'Close time' => $row['close_date'],
                            'Open price' => $row['entry_price'] ?? '—',
                            'Close price' => $row['exit_price'] ?? '—',
                            'Commission' => $row['commission'] ?? '—',
                            'Swap' => $row['swap'] ?? '—',
                            'Realized P/L' => $row['filter'] === 'closed' ? $row['profit'] : '—',
                            'Floating P/L' => $row['filter'] === 'open' ? $row['profit'] : '—',
                        ] as $label => $value)
                            <div>
                                <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $label }}</dt>
                                <dd class="mt-1 break-words font-medium text-slate-200">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </article>
            @empty
                <div class="rounded-[1.35rem] border border-dashed border-white/10 px-4 py-8 text-center text-slate-400">
                    No trades match these filters.
                </div>
            @endforelse
        </div>

        <div class="mt-6 hidden overflow-x-auto rounded-[1.4rem] border border-white/6 md:block">
            <table class="min-w-[1280px] divide-y divide-white/6 text-left text-sm text-slate-300">
                <thead class="bg-white/3 text-xs uppercase tracking-[0.18em] text-slate-400">
                    <tr>
                        @foreach (['Ticket / order', 'Symbol', 'Type', 'Lot size', 'Open price', 'Close price', 'Stop loss', 'Take profit', 'Open time', 'Close time', 'Commission', 'Swap', 'Realized P/L', 'Floating P/L', 'Status'] as $heading)
                            <th class="px-4 py-3 font-semibold">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/6">
                    @forelse ($tradeRows as $row)
                        <tr>
                            <td class="px-4 py-4 font-semibold text-white">{{ $row['id'] }}</td>
                            <td class="px-4 py-4">{{ $row['symbol'] }}</td>
                            <td class="px-4 py-4">{{ $row['side'] === '—' ? '—' : strtolower((string) $row['side']) }}</td>
                            <td class="px-4 py-4">{{ $row['volume'] }}</td>
                            <td class="px-4 py-4">{{ $row['entry_price'] ?? '—' }}</td>
                            <td class="px-4 py-4">{{ $row['exit_price'] ?? '—' }}</td>
                            <td class="px-4 py-4">{{ $row['stop_loss'] ?? '—' }}</td>
                            <td class="px-4 py-4">{{ $row['take_profit'] ?? '—' }}</td>
                            <td class="px-4 py-4">{{ $row['open_date'] }}</td>
                            <td class="px-4 py-4">{{ $row['close_date'] }}</td>
                            <td class="px-4 py-4">{{ $row['commission'] ?? '—' }}</td>
                            <td class="px-4 py-4">{{ $row['swap'] ?? '—' }}</td>
                            <td class="px-4 py-4 font-semibold text-white">{{ $row['filter'] === 'closed' ? $row['profit'] : '—' }}</td>
                            <td class="px-4 py-4 font-semibold text-white">{{ $row['filter'] === 'open' ? $row['profit'] : '—' }}</td>
                            <td class="px-4 py-4">{{ ($row['auto_closed_by_breach'] ?? false) ? 'auto closed by breach' : strtolower((string) ($row['status'] ?? ($row['filter'] === 'open' ? 'open' : 'closed'))) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="px-4 py-8 text-center text-slate-400">No trades match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">
            {{ $tradeRows->links() }}
        </div>
    </section>

    <section class="mt-8 surface-panel rounded-[2rem] p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-300">MT5 Diagnostics</p>
        <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                'Latest sync log' => $diagnostics['latest_sync_log_status'],
                'Sync message' => $diagnostics['latest_sync_log_message'],
                'Sync error' => $diagnostics['latest_sync_log_error'],
                'Sync completed' => $diagnostics['latest_sync_log_completed_at'],
                'Rejected reason' => $diagnostics['last_rejected_reason'],
                'Ignored reason' => $diagnostics['last_ignored_reason'],
                'MT5 disable event' => $diagnostics['disable_event'],
                'MT5 disable status' => $diagnostics['disable_status'],
                'MT5 disable requested' => $diagnostics['disable_requested_at'],
                'MT5 disable last attempt' => $diagnostics['disable_last_attempt_at'],
                'MT5 disable attempts' => $diagnostics['disable_attempts'],
                'MT5 disable executed' => $diagnostics['disable_executed_at'],
                'MT5 disable ack' => $diagnostics['disable_acknowledged_at'],
                'MT5 disable source' => $diagnostics['disable_source'],
                'Bridge status' => $diagnostics['disable_bridge_status'],
                'MT5 trading permission' => $diagnostics['mt5_trading_permission_state'],
                'Position close status' => $diagnostics['close_status'],
                'Position close success' => $diagnostics['close_success'],
                'Closed positions' => $diagnostics['closed_positions_count'],
                'Positions remaining' => $diagnostics['positions_remaining_count'],
                'Close result' => $diagnostics['close_result_message'],
                'Disable failure reason' => $diagnostics['disable_failure_reason'],
                'MT5 disable error' => $diagnostics['disable_error'],
            ] as $label => $value)
                <div class="rounded-2xl border border-white/6 bg-black/15 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $label }}</dt>
                    <dd class="mt-2 break-words text-sm font-semibold text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </div>

        @if (! empty($diagnostics['last_payload_summary']))
            <div class="mt-5 rounded-2xl border border-white/6 bg-black/15 p-4">
                <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Last payload summary</dt>
                <dd class="mt-3 grid gap-2 text-sm text-slate-300 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($diagnostics['last_payload_summary'] as $key => $value)
                        <span><strong class="text-white">{{ str($key)->replace('_', ' ')->title() }}:</strong> {{ is_scalar($value) || $value === null ? ($value ?? 'N/A') : json_encode($value) }}</span>
                    @endforeach
                </dd>
            </div>
        @endif

        <div class="mt-5 rounded-2xl border border-white/6 bg-black/15 p-4">
            <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Disable response and permission payloads</dt>
            <dd class="mt-3 grid gap-2 font-mono text-[11px] leading-5 text-slate-300 lg:grid-cols-2">
                <span><strong class="text-white">Disable response:</strong> {{ $diagnostics['disable_response_payload'] }}</span>
                <span><strong class="text-white">MT5 permission:</strong> {{ $diagnostics['mt5_trading_permission_payload'] }}</span>
                <span><strong class="text-white">Closed position tickets:</strong> {{ $diagnostics['closed_position_tickets'] }}</span>
                <span><strong class="text-white">Closed position identifiers:</strong> {{ $diagnostics['closed_position_identifiers'] }}</span>
                <span><strong class="text-white">Failed close tickets:</strong> {{ $diagnostics['failed_close_tickets'] }}</span>
                <span><strong class="text-white">Close failed reasons:</strong> {{ $diagnostics['close_failed_reasons'] }}</span>
            </dd>
        </div>
    </section>
@endsection
