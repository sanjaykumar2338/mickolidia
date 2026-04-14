@php
    $sidebarRules = ! empty($primaryAccount)
        ? [
            ['label' => __('site.dashboard.labels.target'), 'value' => $primaryAccount['profit_target_percent']],
            ['label' => __('site.dashboard.labels.daily_loss'), 'value' => $primaryAccount['daily_drawdown_limit_percent']],
            ['label' => __('site.dashboard.labels.max_loss'), 'value' => $primaryAccount['max_drawdown_limit_percent']],
            ['label' => __('site.dashboard.labels.min_days'), 'value' => $primaryAccount['minimum_trading_days']],
        ]
        : [];

    $dashboardNavLinks = [
        [
            'route' => route('dashboard'),
            'active' => request()->routeIs('dashboard'),
            'label' => __('site.dashboard.nav.overview'),
        ],
        [
            'route' => route('dashboard.accounts'),
            'active' => request()->routeIs('dashboard.accounts'),
            'label' => __('site.dashboard.nav.accounts'),
        ],
        [
            'route' => route('dashboard.payouts'),
            'active' => request()->routeIs('dashboard.payouts'),
            'label' => __('site.dashboard.nav.payouts'),
        ],
        [
            'route' => route('dashboard.settings'),
            'active' => request()->routeIs('dashboard.settings'),
            'label' => __('site.dashboard.nav.settings'),
        ],
    ];
@endphp

<aside class="dashboard-scrollbar border-b border-white/6 bg-slate-950/86 px-4 py-4 backdrop-blur-xl lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto lg:border-b-0 lg:border-r lg:px-5 lg:py-5">
    <div class="lg:hidden" data-mobile-nav>
        <div class="rounded-[1.6rem] border border-white/8 bg-slate-950/92 p-3 shadow-[0_22px_70px_rgba(2,6,23,0.38)]">
            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('home') }}" class="flex min-w-0 flex-1 items-center gap-3 rounded-2xl border border-white/6 bg-white/4 p-2.5 pr-3 transition hover:border-amber-400/20 hover:bg-white/6">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center">
                        <img src="{{ asset('newfolder/IMG_8542.png') }}" alt="Wolforix" class="h-full w-full object-contain">
                    </span>
                    <span class="min-w-0">
                        <span class="inline-flex items-start text-[0.74rem] font-semibold tracking-[0.24em] text-amber-300">
                            <span>WOLFORIX</span>
                            <span class="ml-1 text-[0.58em] leading-none tracking-normal text-amber-200">®</span>
                        </span>
                        <span class="mt-1 block truncate text-[11px] leading-4 text-slate-400">{{ __('site.dashboard.sidebar_label') }}</span>
                    </span>
                </a>

                <div class="flex shrink-0 items-center gap-2">
                    <x-language-switcher compact />

                    <button
                        type="button"
                        data-mobile-nav-toggle
                        data-open-label="{{ __('site.nav.menu_open') }}"
                        data-close-label="{{ __('site.nav.menu_close') }}"
                        aria-expanded="false"
                        aria-label="{{ __('site.nav.menu_open') }}"
                        class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-slate-100 shadow-inner shadow-white/5 transition hover:border-amber-300/25 hover:bg-amber-300/10 hover:text-white"
                    >
                        <svg data-mobile-nav-open-icon xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                        <svg data-mobile-nav-close-icon xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path stroke-linecap="round" d="M6 6 18 18M18 6 6 18" />
                        </svg>
                    </button>
                </div>
            </div>

            <div data-mobile-nav-panel class="hidden pt-3">
                <div class="rounded-[1.35rem] border border-white/8 bg-white/[0.035] p-3">
                    <div class="rounded-2xl border border-amber-400/15 bg-amber-400/10 px-4 py-3 text-sm font-medium text-amber-50">
                        {{ ! empty($primaryAccount) ? $primaryAccount['plan'] : __('site.dashboard.simulated_badge') }}
                    </div>

                    <nav class="mt-3 grid gap-2">
                        @foreach ($dashboardNavLinks as $link)
                            <a
                                href="{{ $link['route'] }}"
                                data-mobile-nav-close
                                class="{{ $link['active'] ? 'border-amber-400/30 bg-amber-400/12 text-white' : 'border-white/6 bg-white/2 text-slate-300 hover:bg-white/5 hover:text-white' }} flex min-h-12 w-full items-center justify-between rounded-2xl border px-4 py-3 text-sm font-medium transition"
                            >
                                <span>{{ $link['label'] }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" />
                                </svg>
                            </a>
                        @endforeach
                    </nav>

                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button
                            type="submit"
                            class="flex min-h-12 w-full items-center justify-between rounded-2xl border border-white/6 bg-white/2 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white"
                        >
                            <span>{{ __('site.nav.logout') }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 5h3a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-3" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 17l5-5-5-5" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H4" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="hidden lg:block">
        <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-3xl border border-white/6 bg-white/4 p-4 transition hover:border-amber-400/20 hover:bg-white/6">
            <div class="flex h-12 w-12 items-center justify-center">
                <img src="{{ asset('newfolder/IMG_8542.png') }}" alt="Wolforix" class="h-full w-full object-contain">
            </div>
            <div class="min-w-0">
                <p class="inline-flex items-start text-sm font-semibold tracking-[0.28em] text-amber-300">
                    <span>WOLFORIX</span>
                    <span class="ml-1 text-[0.58em] leading-none tracking-normal text-amber-200">®</span>
                </p>
                <p class="mt-1 text-xs text-slate-400">{{ __('site.dashboard.sidebar_label') }}</p>
            </div>
        </a>

        @if (! empty($primaryAccount))
            <div class="mt-6 rounded-3xl border border-amber-400/15 bg-amber-400/10 px-4 py-3 text-sm text-amber-50">
                {{ $primaryAccount['plan'] }}
            </div>
        @else
            <div class="mt-6 rounded-3xl border border-amber-400/15 bg-amber-400/10 px-4 py-3 text-sm text-amber-50">
                {{ __('site.dashboard.simulated_badge') }}
            </div>
        @endif

        <nav class="mt-6 space-y-2">
            @foreach ($dashboardNavLinks as $link)
                <a href="{{ $link['route'] }}" class="{{ $link['active'] ? 'border-amber-400/30 bg-amber-400/12 text-white' : 'border-white/6 bg-white/2 text-slate-300 hover:bg-white/5 hover:text-white' }} block rounded-2xl border px-4 py-3 text-sm font-medium transition">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button
                type="submit"
                class="flex w-full items-center justify-between rounded-2xl border border-white/6 bg-white/2 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white"
            >
                <span>{{ __('site.nav.logout') }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5h3a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-3" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 17l5-5-5-5" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H4" />
                </svg>
            </button>
        </form>

        @if (! empty($primaryAccount))
            <div class="mt-6 space-y-6">
                <div class="surface-card rounded-3xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('Primary account') }}</p>
                    <p class="mt-3 text-lg font-semibold text-white">{{ $primaryAccount['reference'] }}</p>
                    <p class="mt-2 text-sm text-slate-400">{{ $primaryAccount['platform_account_id'] }}</p>

                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-400">{{ __('Challenge balance') }}</dt>
                            <dd class="font-semibold text-white">{{ $primaryAccount['balance'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-400">{{ __('Challenge equity') }}</dt>
                            <dd class="font-semibold text-white">{{ $primaryAccount['equity'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-400">{{ __('Floating P&L') }}</dt>
                            <dd class="font-semibold {{ $primaryAccount['floating_pnl_tone'] === 'rose' ? 'text-rose-100' : ($primaryAccount['floating_pnl_tone'] === 'emerald' ? 'text-emerald-100' : 'text-white') }}">
                                {{ $primaryAccount['floating_pnl'] }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="surface-card rounded-3xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('site.dashboard.overview.rules_title') }}</p>
                    <dl class="mt-4 space-y-3 text-sm">
                        @foreach ($sidebarRules as $rule)
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-slate-400">{{ $rule['label'] }}</dt>
                                <dd class="font-semibold text-white">{{ $rule['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>
        @endif
    </div>
</aside>
