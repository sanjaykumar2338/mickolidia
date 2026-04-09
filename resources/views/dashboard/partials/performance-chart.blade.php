@php
    $defaultRange = $performanceChart['ranges'][$performanceChart['default_range']] ?? null;
    $defaultPoints = $defaultRange['points'] ?? [];
    $defaultCount = count($defaultPoints);
    $midIndex = $defaultCount > 0 ? (int) floor(($defaultCount - 1) / 2) : 0;
    $changeToneClasses = [
        'amber' => 'text-amber-100',
        'emerald' => 'text-emerald-100',
        'rose' => 'text-rose-100',
        'sky' => 'text-sky-100',
        'slate' => 'text-white',
    ];
@endphp

<section
    class="surface-panel rounded-[2rem] p-5 sm:p-6"
    data-dashboard-chart
    data-dashboard-chart-payload='@json($performanceChart)'
>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">Performance curve</p>
            <h3 class="mt-3 text-2xl font-semibold text-white">Balance and equity trend</h3>
            <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-400">
                The chart reads from the real synced account snapshots already stored for this dashboard.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach ($performanceChart['ranges'] as $key => $range)
                <button
                    type="button"
                    class="rounded-full border px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] transition {{ $key === $performanceChart['default_range'] ? 'border-amber-400/25 bg-amber-400/15 text-white' : 'border-white/10 bg-white/5 text-slate-300 hover:border-white/20 hover:bg-white/10 hover:text-white' }} {{ $range['is_available'] ? '' : 'cursor-not-allowed opacity-50' }}"
                    data-dashboard-chart-range="{{ $key }}"
                    @disabled(! $range['is_available'])
                >
                    {{ $range['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(16rem,0.42fr)]">
        <div class="min-w-0 rounded-[1.85rem] border border-white/8 bg-black/18 p-4 sm:p-5">
            <div class="dashboard-chart-grid relative overflow-hidden rounded-[1.45rem] border border-white/6 bg-slate-950/70 p-4 sm:p-5">
                <div class="absolute inset-x-0 top-0 h-20 bg-gradient-to-b from-amber-400/6 to-transparent"></div>
                <div class="relative h-64 sm:h-72">
                    <div
                        class="absolute inset-0 flex items-center justify-center px-6 text-center text-sm leading-7 text-slate-400 {{ $performanceChart['is_available'] ? 'hidden' : '' }}"
                        data-dashboard-chart-empty
                    >
                        {{ $performanceChart['empty_message'] }}
                    </div>

                    <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 h-full w-full overflow-visible">
                        <defs>
                            <linearGradient id="dashboard-balance-fill" x1="0%" x2="0%" y1="0%" y2="100%">
                                <stop offset="0%" stop-color="rgba(244, 183, 74, 0.24)" />
                                <stop offset="100%" stop-color="rgba(244, 183, 74, 0)" />
                            </linearGradient>
                        </defs>
                        <path d="" fill="url(#dashboard-balance-fill)" data-dashboard-chart-area></path>
                        <path d="" fill="none" stroke="#f4b74a" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" data-dashboard-chart-balance></path>
                        <path d="" fill="none" stroke="#38bdf8" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" stroke-dasharray="2 3" data-dashboard-chart-equity></path>
                    </svg>
                </div>

                <div class="mt-4 flex items-center gap-5 text-xs text-slate-400">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-amber-300"></span>
                        <span>Balance</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-sky-300"></span>
                        <span>Equity</span>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-3 gap-3 text-xs text-slate-400">
                    <span class="truncate" data-dashboard-chart-label-start>{{ $defaultPoints[0]['label'] ?? '—' }}</span>
                    <span class="truncate text-center" data-dashboard-chart-label-mid>{{ $defaultPoints[$midIndex]['label'] ?? '—' }}</span>
                    <span class="truncate text-right" data-dashboard-chart-label-end>{{ $defaultPoints[max($defaultCount - 1, 0)]['label'] ?? '—' }}</span>
                </div>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
            <article class="rounded-[1.6rem] border border-white/8 bg-black/18 p-4">
                <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-400">Range change</p>
                <p
                    class="mt-3 text-2xl font-semibold {{ $changeToneClasses[$defaultRange['summary']['change_tone'] ?? 'slate'] ?? $changeToneClasses['slate'] }}"
                    data-dashboard-chart-change
                >
                    {{ $defaultRange['summary']['change'] ?? '$0.00' }}
                </p>
                <p class="mt-2 text-xs text-slate-400" data-dashboard-chart-range-hint>{{ $defaultRange['summary']['range_hint'] ?? 'No synced data yet' }}</p>
            </article>

            <article class="rounded-[1.6rem] border border-white/8 bg-black/18 p-4">
                <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-400">Current balance</p>
                <p class="mt-3 text-2xl font-semibold text-white" data-dashboard-chart-balance-value>{{ $defaultRange['summary']['last_balance'] ?? '$0.00' }}</p>
                <p class="mt-2 text-xs text-slate-400">Latest synced balance point</p>
            </article>

            <article class="rounded-[1.6rem] border border-white/8 bg-black/18 p-4">
                <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-400">Current equity</p>
                <p class="mt-3 text-2xl font-semibold text-white" data-dashboard-chart-equity-value>{{ $defaultRange['summary']['last_equity'] ?? '$0.00' }}</p>
                <p class="mt-2 text-xs text-slate-400">Latest synced equity point</p>
            </article>

            <article class="rounded-[1.6rem] border border-white/8 bg-black/18 p-4">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <div>
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-400">Range high</p>
                        <p class="mt-3 text-xl font-semibold text-white" data-dashboard-chart-high>{{ $defaultRange['summary']['high'] ?? '$0.00' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-400">Range low</p>
                        <p class="mt-3 text-xl font-semibold text-white" data-dashboard-chart-low>{{ $defaultRange['summary']['low'] ?? '$0.00' }}</p>
                    </div>
                </div>
            </article>
        </div>
    </div>
</section>
