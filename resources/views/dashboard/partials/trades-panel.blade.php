@php
    $filterClass = 'border-white/10 bg-white/5 text-slate-300 hover:border-white/20 hover:bg-white/10 hover:text-white';
    $activeFilterClass = 'border-amber-400/25 bg-amber-400/15 text-white';
    $profitToneClasses = [
        'amber' => 'text-amber-100',
        'emerald' => 'text-emerald-100',
        'rose' => 'text-rose-100',
        'sky' => 'text-sky-100',
        'slate' => 'text-white',
    ];
@endphp

<section class="surface-panel rounded-[2rem] p-5 sm:p-6" data-dashboard-trades data-dashboard-trades-summary='@json($tradesPanel['summary'])'>
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{{ __('Trade history') }}</p>
            <h3 class="mt-3 text-2xl font-semibold text-white">{{ __('Open and closed trades') }}</h3>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-400">
                {{ $tradesPanel['message'] }}
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
                            <p class="truncate text-base font-semibold text-white">{{ $row['symbol'] }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.22em] text-slate-400">{{ $row['status'] }} • {{ $row['side'] }}</p>
                        </div>
                        <span class="rounded-full border border-white/10 bg-white/6 px-3 py-1 text-xs font-semibold text-slate-200">
                            {{ $row['id'] }}
                        </span>
                    </div>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('Open') }}</dt>
                            <dd class="mt-1 text-sm text-white">{{ $row['open_date'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('Close') }}</dt>
                            <dd class="mt-1 text-sm text-white">{{ $row['close_date'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('Volume') }}</dt>
                            <dd class="mt-1 text-sm text-white">{{ $row['volume'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('P&L') }}</dt>
                            <dd class="mt-1 text-sm font-semibold {{ $profitToneClasses[$row['profit_tone']] ?? $profitToneClasses['slate'] }}">{{ $row['profit'] }}</dd>
                        </div>
                    </dl>
                </article>
            @endforeach
        </div>

        <div class="mt-5 hidden overflow-hidden rounded-[1.7rem] border border-white/8 bg-black/18 md:block">
            <div class="dashboard-table-wrap overflow-x-auto">
                <table class="min-w-full divide-y divide-white/8 text-left text-sm">
                    <thead class="bg-white/4 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-slate-400">
                        <tr>
                            <th class="px-4 py-4">{{ __('Ticket') }}</th>
                            <th class="px-4 py-4">{{ __('Symbol') }}</th>
                            <th class="px-4 py-4">{{ __('Side') }}</th>
                            <th class="px-4 py-4">{{ __('Open date') }}</th>
                            <th class="px-4 py-4">{{ __('Close date') }}</th>
                            <th class="px-4 py-4">{{ __('Volume') }}</th>
                            <th class="px-4 py-4 text-right">{{ __('P&L') }}</th>
                            <th class="px-4 py-4">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/6">
                        @foreach ($tradesPanel['rows'] as $row)
                            <tr data-dashboard-trades-row data-trade-filter="{{ $row['filter'] }}" class="align-top text-slate-300 transition hover:bg-white/5">
                                <td class="px-4 py-4 font-semibold text-white">{{ $row['id'] }}</td>
                                <td class="px-4 py-4 text-white">{{ $row['symbol'] }}</td>
                                <td class="px-4 py-4">{{ $row['side'] }}</td>
                                <td class="px-4 py-4">{{ $row['open_date'] }}</td>
                                <td class="px-4 py-4">{{ $row['close_date'] }}</td>
                                <td class="px-4 py-4">{{ $row['volume'] }}</td>
                                <td class="px-4 py-4 text-right font-semibold {{ $profitToneClasses[$row['profit_tone']] ?? $profitToneClasses['slate'] }}">{{ $row['profit'] }}</td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full border border-white/10 bg-white/6 px-3 py-1 text-xs font-semibold text-slate-200">
                                        {{ $row['status'] }}
                                    </span>
                                </td>
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
