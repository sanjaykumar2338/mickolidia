@php
    $toneTextClasses = [
        'amber' => 'text-amber-100',
        'emerald' => 'text-emerald-100',
        'rose' => 'text-rose-100',
        'sky' => 'text-sky-100',
        'slate' => 'text-white',
    ];
    $winRateValue = (float) ($insights['win_rate_value'] ?? 0);
    $winRingLabel = ($insights['win_rate_available'] ?? false) ? $insights['win_rate'] : '0%';
@endphp

<section class="surface-panel overflow-hidden rounded-[2rem] p-5 sm:p-6 lg:p-7">
    <div class="grid gap-4 xl:grid-cols-[minmax(0,0.82fr)_minmax(0,1.18fr)] xl:items-start">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{{ __('Trading command center') }}</p>
            <h3 class="mt-3 text-2xl font-semibold text-white">{{ __('Everything important within reach') }}</h3>
        </div>
        <p class="max-w-4xl text-sm leading-7 text-slate-400 xl:pt-2">
            {{ __('Account access, win ratio, balance, first-trade timing, and traded symbols are grouped here for faster mobile scanning.') }}
        </p>
    </div>

    <div class="mt-6 grid items-stretch gap-4 lg:grid-cols-3">
        <article class="flex h-full flex-col rounded-[1.75rem] border border-amber-400/14 bg-amber-400/8 p-5 shadow-[0_22px_60px_rgba(2,6,23,0.26)]">
            <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-200">{{ __('Current balance') }}</p>
            <p class="mt-4 text-3xl font-semibold text-white sm:text-4xl">{{ $insights['balance'] }}</p>
            <p class="mt-2 text-sm leading-6 text-amber-50/70">{{ $insights['balance_hint'] }}</p>

            <div class="mt-5 rounded-[1.35rem] border border-white/8 bg-black/20 p-4">
                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('Time since first trade') }}</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ $insights['time_since_first_trade'] ?? __('No trade yet') }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $insights['first_trade_at'] ?? __('Earliest synced trade row appears here.') }}</p>

                @if (! empty($insights['time_since_first_trade_segments']))
                    <div class="mt-4 grid grid-cols-4 gap-2">
                        @foreach ([
                            __('Day') => $insights['time_since_first_trade_segments']['days'],
                            __('Hr') => $insights['time_since_first_trade_segments']['hours'],
                            __('Min') => $insights['time_since_first_trade_segments']['minutes'],
                            __('Sec') => $insights['time_since_first_trade_segments']['seconds'],
                        ] as $label => $value)
                            <div class="rounded-2xl border border-white/6 bg-white/4 px-2 py-2 text-center">
                                <p class="text-lg font-semibold text-white">{{ $value }}</p>
                                <p class="mt-1 text-[0.62rem] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $label }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if (! empty($insights['certificate_url']))
                <a href="{{ $insights['certificate_url'] }}" class="mt-4 inline-flex w-full items-center justify-center rounded-full border border-amber-300/28 bg-amber-300/14 px-4 py-3 text-sm font-semibold text-amber-50 transition hover:border-amber-200/50 hover:bg-amber-300/20">
                    {{ __('Download certificate') }}
                </a>
            @endif
        </article>

        <article class="flex h-full flex-col rounded-[1.75rem] border border-white/8 bg-black/18 p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.26em] text-slate-400">{{ __('Win ratio') }}</p>
                </div>
                <span class="rounded-full border border-white/10 bg-white/6 px-3 py-1 text-xs font-semibold text-slate-200">
                    {{ __('Closed') }} {{ $insights['closed_trades'] }}
                </span>
            </div>

            <div class="mt-6 flex flex-1 flex-col items-center justify-center text-center">
                <div
                    class="grid h-32 w-32 shrink-0 place-items-center rounded-full p-2"
                    style="background: conic-gradient(#f4b74a 0 {{ $winRateValue }}%, rgba(148, 163, 184, 0.16) {{ $winRateValue }}% 100%);"
                    aria-hidden="true"
                >
                    <div class="grid h-full w-full place-items-center rounded-full border border-white/8 bg-slate-950">
                        <span class="text-xl font-semibold text-white">{{ $winRingLabel }}</span>
                    </div>
                </div>
                <p class="mt-5 text-2xl font-semibold leading-tight text-white">{{ $insights['win_rate'] }}</p>
                <p class="mt-2 max-w-xs text-sm leading-6 text-slate-400">
                    {{ $insights['win_rate_available'] ? __('Winning closed trades vs total closed trades.') : __('Low-data state until closed trades sync.') }}
                </p>
            </div>

            <p class="mt-5 rounded-[1.25rem] border border-white/6 bg-white/4 px-4 py-3 text-sm leading-6 text-slate-400">
                {{ $insights['win_rate_hint'] }}
            </p>
        </article>

        <article class="flex h-full flex-col rounded-[1.75rem] border border-white/8 bg-black/18 p-5">
            <div class="flex flex-col items-center gap-4 text-center">
                <div
                    class="grid h-32 w-32 shrink-0 place-items-center rounded-full p-2"
                    style="{{ $insights['instrument_ring_style'] }}"
                    aria-hidden="true"
                >
                    <div class="grid h-full w-full place-items-center rounded-full border border-white/8 bg-slate-950 text-center">
                        <span class="dashboard-command-wolfi-avatar">
                            <img
                                src="{{ asset('new-wolfy.webp') }}"
                                alt=""
                                class="dashboard-command-wolfi-image"
                                loading="lazy"
                                decoding="async"
                            >
                            <span class="dashboard-command-wolfi-core" aria-hidden="true"></span>
                        </span>
                    </div>
                </div>

                <div class="max-w-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.26em] text-slate-400">{{ __('Most traded instruments') }}</p>
                    <p class="mt-3 text-sm leading-6 text-slate-400">{{ $insights['instrument_message'] }}</p>
                </div>
            </div>

            @if (! empty($insights['top_instruments']))
                <div class="mt-5 space-y-3">
                    @foreach ($insights['top_instruments'] as $instrument)
                        <div class="rounded-[1.25rem] border border-white/6 bg-white/4 px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $instrument['color'] }}"></span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-white">{{ $instrument['symbol'] }}</p>
                                        <p class="mt-1 text-xs text-slate-400">{{ $instrument['count_label'] }} • {{ __('Vol') }} {{ $instrument['volume'] }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold {{ $toneTextClasses[$instrument['pnl_tone']] ?? $toneTextClasses['slate'] }}">{{ $instrument['pnl'] }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $instrument['share_label'] }}</p>
                                </div>
                            </div>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/8">
                                <div class="h-full rounded-full" style="width: {{ $instrument['share'] }}%; background-color: {{ $instrument['color'] }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-5 rounded-[1.35rem] border border-dashed border-white/12 bg-white/3 px-4 py-5 text-sm leading-7 text-slate-400">
                    {{ __('Top symbols will populate from synced open positions and closed trade history.') }}
                </div>
            @endif
        </article>

    </div>
</section>
