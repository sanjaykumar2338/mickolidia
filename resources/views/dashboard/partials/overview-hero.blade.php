@php
    $badgeToneClasses = [
        'amber' => 'border-amber-400/20 bg-amber-400/12 text-amber-100',
        'emerald' => 'border-emerald-400/20 bg-emerald-500/12 text-emerald-100',
        'rose' => 'border-rose-400/20 bg-rose-500/12 text-rose-100',
        'sky' => 'border-sky-400/20 bg-sky-500/12 text-sky-100',
        'slate' => 'border-white/10 bg-white/6 text-slate-200',
    ];
    $metricToneClasses = [
        'amber' => 'text-amber-100',
        'emerald' => 'text-emerald-100',
        'rose' => 'text-rose-100',
        'sky' => 'text-sky-100',
        'slate' => 'text-white',
    ];
    $syncToneClass = $badgeToneClasses[$hero['sync_freshness']['tone'] ?? 'slate'] ?? $badgeToneClasses['slate'];
    $primaryMetricCards = [
        [
            'label' => __('Balance'),
            'value' => $insights['balance'] ?? $primaryAccount['balance'],
            'hint' => $insights['balance_hint'] ?? __('Challenge-relative current balance'),
            'tone' => 'amber',
        ],
        [
            'label' => __('Equity'),
            'value' => $insights['equity'] ?? $primaryAccount['equity'],
            'hint' => $insights['equity_hint'] ?? __('Challenge-relative equity'),
            'tone' => 'sky',
        ],
        [
            'label' => __('Trading days'),
            'value' => $insights['trading_days'] ?? $primaryAccount['trading_days_completed'].' / '.$primaryAccount['minimum_trading_days'],
            'hint' => __('Current / required'),
            'tone' => 'slate',
        ],
        [
            'label' => __('Win ratio'),
            'value' => $insights['win_rate'] ?? __('No closed trades'),
            'hint' => $insights['win_rate_hint'] ?? __('Closed trades only'),
            'tone' => ($insights['win_rate_value'] ?? 0) >= 50 ? 'emerald' : 'amber',
        ],
    ];
@endphp

<section class="dashboard-hero surface-panel relative overflow-hidden rounded-[2rem] p-5 sm:p-6 lg:p-8">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute left-[-8rem] top-[-8rem] h-56 w-56 rounded-full bg-amber-400/10 blur-3xl"></div>
        <div class="absolute bottom-[-10rem] right-[-6rem] h-64 w-64 rounded-full bg-sky-500/12 blur-3xl"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.04),transparent_32%),linear-gradient(180deg,rgba(255,255,255,0.03),transparent_22%)]"></div>
    </div>

    <div class="relative grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.8fr)] xl:items-start">
        <div class="min-w-0 space-y-5">
            <div class="flex flex-wrap gap-2">
                @foreach ($hero['badges'] as $badge)
                    <span class="{{ $badgeToneClasses[$badge['tone']] ?? $badgeToneClasses['slate'] }} inline-flex rounded-full border px-3 py-1 text-[0.7rem] font-semibold uppercase tracking-[0.22em]">
                        {{ $badge['label'] }}
                    </span>
                @endforeach
            </div>

            <div class="max-w-4xl">
                <p class="text-xs font-semibold uppercase tracking-[0.32em] text-amber-300">{{ __('Account summary') }}</p>
                <h2 class="mt-3 text-3xl font-semibold text-white sm:text-4xl">{{ $hero['title'] }}</h2>
                <p class="mt-3 break-words text-sm leading-7 text-slate-400">{{ $hero['subtitle'] }}</p>
            </div>

            <article class="rounded-[1.8rem] border border-white/8 bg-black/18 p-4 sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="{{ $badgeToneClasses[$primaryAccount['status_tone'] ?? 'slate'] ?? $badgeToneClasses['slate'] }} rounded-full border px-3 py-1 text-xs font-semibold">
                                {{ $primaryAccount['challenge_status'] }}
                            </span>
                            <span class="rounded-full border border-white/10 bg-white/6 px-3 py-1 text-xs font-semibold text-slate-200">
                                {{ $primaryAccount['account_size'] }}
                            </span>
                        </div>
                        <p class="mt-4 text-xl font-semibold text-white">{{ $primaryAccount['reference'] }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-400">
                            {{ $primaryAccount['plan'] }} • {{ __('Start trade period') }}: {{ $primaryAccount['start_date'] }}
                        </p>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-3 lg:min-w-[28rem]">
                        <button type="button" data-dashboard-modal-open="credentials" class="inline-flex items-center justify-center gap-2 rounded-full border border-amber-400/24 bg-amber-400/12 px-4 py-3 text-sm font-semibold text-amber-50 transition hover:border-amber-300/45 hover:bg-amber-400/18">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M14.6 9.4a4.6 4.6 0 1 1-1.7-3.57l1.94 1.94h2.06v2.06h2.05v2.05h2.05v2.2h-4.37l-2.03-2.03a4.5 4.5 0 0 1-1.7.33Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M7.8 12.1h.01" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" />
                            </svg>
                            {{ __('Credentials') }}
                        </button>
                        <button type="button" data-dashboard-modal-open="share" class="inline-flex items-center justify-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/10">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M8.8 12.7 15.2 16M15.2 8 8.8 11.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                <circle cx="6.5" cy="12" r="2.5" stroke="currentColor" stroke-width="1.7" />
                                <circle cx="17.5" cy="6.7" r="2.5" stroke="currentColor" stroke-width="1.7" />
                                <circle cx="17.5" cy="17.3" r="2.5" stroke="currentColor" stroke-width="1.7" />
                            </svg>
                            {{ __('Share metrics') }}
                        </button>
                        <a href="#dashboard-metrics" class="inline-flex items-center justify-center rounded-full border border-sky-300/20 bg-sky-400/10 px-4 py-3 text-sm font-semibold text-sky-50 transition hover:border-sky-200/40 hover:bg-sky-400/16">
                            {{ __('Go to metrics') }}
                        </a>
                    </div>
                </div>
            </article>

            @if (! empty($hero['state_notice']))
                <div class="{{ $badgeToneClasses[$hero['state_notice']['tone']] ?? $badgeToneClasses['slate'] }} rounded-[1.6rem] border px-4 py-3 text-sm leading-7">
                    <span class="font-semibold text-white">{{ $hero['state_notice']['title'] }}:</span>
                    {{ $hero['state_notice']['message'] }}
                </div>
            @endif

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($primaryMetricCards as $metric)
                    <article class="rounded-[1.7rem] border border-white/8 bg-black/18 p-4 shadow-[0_18px_45px_rgba(2,6,23,0.22)]">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-400">{{ $metric['label'] }}</p>
                        <p class="mt-3 text-2xl font-semibold {{ $metricToneClasses[$metric['tone']] ?? $metricToneClasses['slate'] }}">
                            {{ $metric['value'] }}
                        </p>
                        <p class="mt-2 text-xs leading-5 text-slate-400">{{ $metric['hint'] }}</p>
                    </article>
                @endforeach
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($hero['metrics'] as $metric)
                    <article class="rounded-[1.7rem] border border-white/8 bg-black/18 p-4 shadow-[0_18px_45px_rgba(2,6,23,0.22)]">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-400">{{ $metric['label'] }}</p>
                        <p class="mt-3 text-2xl font-semibold {{ $metricToneClasses[$metric['tone']] ?? $metricToneClasses['slate'] }}">
                            {{ $metric['value'] }}
                        </p>
                        <p class="mt-2 text-xs text-slate-400">{{ $metric['hint'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <article class="surface-card rounded-[1.85rem] p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('Target progress') }}</p>
                <div class="mt-4 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-4xl font-semibold text-white">{{ $primaryAccount['progress_label'] }}</p>
                        <p class="mt-2 text-sm text-slate-400">{{ __('toward the current phase target') }}</p>
                    </div>
                    <span class="{{ $syncToneClass }} inline-flex rounded-full border px-3 py-1 text-xs font-semibold">
                        {{ $hero['sync_freshness']['label'] }}
                    </span>
                </div>
                <p class="mt-4 text-sm leading-7 text-slate-400">{{ $hero['sync_freshness']['hint'] }}</p>
            </article>

            <article class="surface-card rounded-[1.85rem] p-5 sm:col-span-2 xl:col-span-1">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('Account details') }}</p>
                    @if (($primaryAccount['platform_slug'] ?? null) === 'mt5')
                        <button type="button" data-dashboard-modal-open="credentials" class="inline-flex items-center gap-2 rounded-full border border-amber-400/24 bg-amber-400/12 px-3 py-1.5 text-xs font-semibold text-amber-50 transition hover:border-amber-300/40 hover:bg-amber-400/18">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M14.6 9.4a4.6 4.6 0 1 1-1.7-3.57l1.94 1.94h2.06v2.06h2.05v2.05h2.05v2.2h-4.37l-2.03-2.03a4.5 4.5 0 0 1-1.7.33Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M7.8 12.1h.01" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" />
                            </svg>
                            {{ __('MT5 access') }}
                        </button>
                    @endif
                </div>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/4 px-4 py-3">
                        <dt class="text-slate-400">{{ __('Platform') }}</dt>
                        <dd class="font-semibold text-white">{{ $hero['platform'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/4 px-4 py-3">
                        <dt class="text-slate-400">{{ __('Start date') }}</dt>
                        <dd class="font-semibold text-white">{{ $hero['start_date'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/4 px-4 py-3">
                        <dt class="text-slate-400">{{ __('Phase') }}</dt>
                        <dd class="font-semibold text-white">{{ $hero['challenge_phase'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/4 px-4 py-3">
                        <dt class="text-slate-400">{{ __('Status') }}</dt>
                        <dd class="font-semibold text-white">{{ $hero['challenge_status'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/4 px-4 py-3">
                        <dt class="text-slate-400">{{ __('Sync status') }}</dt>
                        <dd class="font-semibold text-white">{{ $hero['sync_status'] }}</dd>
                    </div>
                </dl>
            </article>
        </div>
    </div>
</section>
