@php
    $panelEyebrow = $panelEyebrow ?? __('Trade history');
    $panelTitle = $panelTitle ?? __('Open and closed trades');
    $panelDescription = $panelDescription ?? $tradesPanel['message'];
    $filterClass = 'border-white/10 bg-white/5 text-slate-300 hover:border-white/20 hover:bg-white/10 hover:text-white';
    $activeFilterClass = 'border-amber-400/25 bg-amber-400/15 text-white';
    $profitToneClasses = [
        'amber' => 'text-amber-100',
        'emerald' => 'text-emerald-100',
        'rose' => 'text-rose-100',
        'sky' => 'text-sky-100',
        'slate' => 'text-white',
    ];
    $statusToneClasses = [
        'amber' => 'border-amber-400/25 bg-amber-400/12 text-amber-50',
        'emerald' => 'border-emerald-400/25 bg-emerald-500/12 text-emerald-100',
        'rose' => 'border-rose-400/25 bg-rose-500/12 text-rose-100',
        'slate' => 'border-white/10 bg-white/6 text-slate-200',
    ];
    $sideToneClasses = [
        'sky' => 'border-sky-400/25 bg-sky-500/12 text-sky-100',
        'rose' => 'border-rose-400/25 bg-rose-500/12 text-rose-100',
        'slate' => 'border-white/10 bg-white/6 text-slate-200',
    ];
    $visibleColumns = $tradesPanel['visible_columns'] ?? [
        'entry_price' => false,
        'exit_price' => false,
        'duration' => false,
        'commission' => false,
        'swap' => false,
        'net_result' => false,
    ];
@endphp

<section class="surface-panel rounded-[2rem] p-5 sm:p-6" data-dashboard-trades data-dashboard-trades-summary='@json($tradesPanel["summary"])'>
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{{ $panelEyebrow }}</p>
            <h3 class="mt-3 text-2xl font-semibold text-white">{{ $panelTitle }}</h3>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-400">
                {{ $panelDescription }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach ($tradesPanel['filters'] as $filter)
                <button
                    type="button"
                    class="rounded-full border px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] transition {{ $filter['key'] === 'both' ? $activeFilterClass : $filterClass }}"
                    data-dashboard-trades-filter="{{ $filter['key'] }}"
                >
                    {{ $filter['label'] }}
                    <span class="ml-2 text-[0.72rem] text-slate-400">
                        {{ $tradesPanel['summary'][$filter['key']] ?? 0 }}
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    @if ($tradesPanel['is_available'])
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-[1.6rem] border border-white/8 bg-black/18 px-4 py-3 text-sm text-slate-300">
            <p>
                {{ __('Showing') }}
                <span class="font-semibold text-white" data-dashboard-trades-count>{{ $tradesPanel['summary']['both'] }}</span>
                {{ __('synced trade rows') }}
            </p>
            <p class="text-xs uppercase tracking-[0.24em] text-slate-400">
                {{ __('Source') }}: {{ $tradesPanel['source'] }}
            </p>
        </div>

        <div class="mt-5 space-y-3 md:hidden">
            @foreach ($tradesPanel['rows'] as $row)
                <article
                    class="rounded-[1.55rem] border border-white/8 bg-black/18 p-4"
                    data-dashboard-trades-row
                    data-trade-filter="{{ $row['filter'] }}"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate text-base font-semibold text-white">{{ $row['symbol'] }}</p>
                                <span class="{{ $sideToneClasses[$row['side_tone']] ?? $sideToneClasses['slate'] }} inline-flex rounded-full border px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.18em]">
                                    {{ $row['side'] }}
                                </span>
                                <span class="{{ $statusToneClasses[$row['status_tone']] ?? $statusToneClasses['slate'] }} inline-flex rounded-full border px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.18em]">
                                    {{ $row['status'] }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs uppercase tracking-[0.22em] text-slate-400">{{ __('Ticket') }} • {{ $row['id'] }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('P&L') }}</p>
                            <p class="mt-1 text-base font-semibold {{ $profitToneClasses[$row['profit_tone']] ?? $profitToneClasses['slate'] }}">{{ $row['profit'] }}</p>
                            @if ($visibleColumns['net_result'])
                                <p class="mt-1 text-xs {{ $profitToneClasses[$row['net_result_tone']] ?? $profitToneClasses['slate'] }}">
                                    {{ __('Net') }} {{ $row['net_result'] ?? '—' }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 text-sm">
                        <div>
                            <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('Open') }}</dt>
                            <dd class="mt-1 text-white">{{ $row['open_date'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('Close') }}</dt>
                            <dd class="mt-1 text-white">{{ $row['close_date'] }}</dd>
                        </div>

                        @if ($visibleColumns['duration'])
                            <div>
                                <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('Duration') }}</dt>
                                <dd class="mt-1 text-white">{{ $row['duration'] ?? '—' }}</dd>
                            </div>
                        @endif

                        @if ($visibleColumns['entry_price'])
                            <div>
                                <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('Entry') }}</dt>
                                <dd class="mt-1 text-white">{{ $row['entry_price'] ?? '—' }}</dd>
                            </div>
                        @endif

                        @if ($visibleColumns['exit_price'])
                            <div>
                                <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('Exit') }}</dt>
                                <dd class="mt-1 text-white">{{ $row['exit_price'] ?? '—' }}</dd>
                            </div>
                        @endif

                        <div>
                            <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('Qty') }}</dt>
                            <dd class="mt-1 text-white">{{ $row['volume'] }}</dd>
                        </div>

                        @if ($visibleColumns['commission'])
                            <div>
                                <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('Commission') }}</dt>
                                <dd class="mt-1 text-white">{{ $row['commission'] ?? '—' }}</dd>
                            </div>
                        @endif

                        @if ($visibleColumns['swap'])
                            <div>
                                <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('Swap') }}</dt>
                                <dd class="mt-1 text-white">{{ $row['swap'] ?? '—' }}</dd>
                            </div>
                        @endif
                    </dl>
                </article>
            @endforeach
        </div>

        <div class="mt-5 hidden overflow-hidden rounded-[1.7rem] border border-white/8 bg-black/18 md:block">
            <div class="dashboard-table-wrap overflow-x-auto">
                <table class="min-w-[980px] w-full divide-y divide-white/8 text-left text-sm">
                    <thead class="bg-white/4 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-slate-400">
                        <tr>
                            <th class="px-4 py-4">{{ __('Ticket') }}</th>
                            <th class="px-4 py-4">{{ __('Symbol') }}</th>
                            <th class="px-4 py-4">{{ __('Side') }}</th>
                            <th class="px-4 py-4">{{ __('Status') }}</th>
                            <th class="px-4 py-4">{{ __('Open date') }}</th>
                            <th class="px-4 py-4">{{ __('Close date') }}</th>
                            @if ($visibleColumns['duration'])
                                <th class="px-4 py-4">{{ __('Duration') }}</th>
                            @endif
                            @if ($visibleColumns['entry_price'])
                                <th class="px-4 py-4 text-right">{{ __('Entry') }}</th>
                            @endif
                            @if ($visibleColumns['exit_price'])
                                <th class="px-4 py-4 text-right">{{ __('Exit') }}</th>
                            @endif
                            <th class="px-4 py-4 text-right">{{ __('Qty') }}</th>
                            @if ($visibleColumns['commission'])
                                <th class="px-4 py-4 text-right">{{ __('Commission') }}</th>
                            @endif
                            @if ($visibleColumns['swap'])
                                <th class="px-4 py-4 text-right">{{ __('Swap') }}</th>
                            @endif
                            <th class="px-4 py-4 text-right">{{ __('P&L') }}</th>
                            @if ($visibleColumns['net_result'])
                                <th class="px-4 py-4 text-right">{{ __('Net result') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/6">
                        @foreach ($tradesPanel['rows'] as $row)
                            <tr data-dashboard-trades-row data-trade-filter="{{ $row['filter'] }}" class="align-top text-slate-300 transition hover:bg-white/5">
                                <td class="px-4 py-4 font-semibold text-white">{{ $row['id'] }}</td>
                                <td class="px-4 py-4 text-white">{{ $row['symbol'] }}</td>
                                <td class="px-4 py-4">
                                    <span class="{{ $sideToneClasses[$row['side_tone']] ?? $sideToneClasses['slate'] }} inline-flex rounded-full border px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.18em]">
                                        {{ $row['side'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="{{ $statusToneClasses[$row['status_tone']] ?? $statusToneClasses['slate'] }} inline-flex rounded-full border px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.18em]">
                                        {{ $row['status'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">{{ $row['open_date'] }}</td>
                                <td class="px-4 py-4">{{ $row['close_date'] }}</td>
                                @if ($visibleColumns['duration'])
                                    <td class="px-4 py-4">{{ $row['duration'] ?? '—' }}</td>
                                @endif
                                @if ($visibleColumns['entry_price'])
                                    <td class="px-4 py-4 text-right text-white">{{ $row['entry_price'] ?? '—' }}</td>
                                @endif
                                @if ($visibleColumns['exit_price'])
                                    <td class="px-4 py-4 text-right text-white">{{ $row['exit_price'] ?? '—' }}</td>
                                @endif
                                <td class="px-4 py-4 text-right">{{ $row['volume'] }}</td>
                                @if ($visibleColumns['commission'])
                                    <td class="px-4 py-4 text-right">{{ $row['commission'] ?? '—' }}</td>
                                @endif
                                @if ($visibleColumns['swap'])
                                    <td class="px-4 py-4 text-right">{{ $row['swap'] ?? '—' }}</td>
                                @endif
                                <td class="px-4 py-4 text-right font-semibold {{ $profitToneClasses[$row['profit_tone']] ?? $profitToneClasses['slate'] }}">{{ $row['profit'] }}</td>
                                @if ($visibleColumns['net_result'])
                                    <td class="px-4 py-4 text-right font-semibold {{ $profitToneClasses[$row['net_result_tone']] ?? $profitToneClasses['slate'] }}">
                                        {{ $row['net_result'] ?? '—' }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5 hidden rounded-[1.55rem] border border-dashed border-white/12 bg-white/3 px-4 py-5 text-center text-sm leading-7 text-slate-400" data-dashboard-trades-empty>
            {{ __('No trades match the selected filter right now.') }}
        </div>
    @else
        <div class="mt-6 rounded-[1.7rem] border border-dashed border-white/12 bg-white/3 px-5 py-6 text-sm leading-7 text-slate-400">
            {{ $tradesPanel['message'] }}
        </div>
    @endif
</section>
