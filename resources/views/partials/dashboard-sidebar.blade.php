@php
    $sidebarRules = ! empty($primaryAccount)
        ? [
            ['label' => __('site.dashboard.labels.target'), 'value' => $primaryAccount['profit_target_percent']],
            ['label' => __('site.dashboard.labels.daily_loss'), 'value' => $primaryAccount['daily_drawdown_limit_percent']],
            ['label' => __('site.dashboard.labels.max_loss'), 'value' => $primaryAccount['max_drawdown_limit_percent']],
            ['label' => __('site.dashboard.labels.min_days'), 'value' => $primaryAccount['minimum_trading_days']],
        ]
        : [];
@endphp

<aside class="border-b border-white/6 bg-slate-950/86 px-4 py-5 backdrop-blur-xl lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto lg:border-b-0 lg:border-r lg:px-5">
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

    <div class="mt-4 lg:hidden">
        <x-language-switcher compact />
    </div>

    @if (! empty($primaryAccount))
        <div class="mt-6 rounded-3xl border border-amber-400/15 bg-amber-400/10 px-4 py-3 text-sm text-amber-50">
            {{ $primaryAccount['plan'] }}
        </div>
    @else
        <div class="mt-6 rounded-3xl border border-amber-400/15 bg-amber-400/10 px-4 py-3 text-sm text-amber-50">
            {{ __('site.dashboard.simulated_badge') }}
        </div>
    @endif

    <nav class="mt-6 flex gap-2 overflow-x-auto pb-1 lg:block lg:space-y-2">
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'border-amber-400/30 bg-amber-400/12 text-white' : 'border-white/6 bg-white/2 text-slate-300 hover:bg-white/5 hover:text-white' }} shrink-0 whitespace-nowrap rounded-2xl border px-4 py-3 text-sm font-medium transition">
            {{ __('site.dashboard.nav.overview') }}
        </a>
        <a href="{{ route('dashboard.accounts') }}" class="{{ request()->routeIs('dashboard.accounts') ? 'border-amber-400/30 bg-amber-400/12 text-white' : 'border-white/6 bg-white/2 text-slate-300 hover:bg-white/5 hover:text-white' }} shrink-0 whitespace-nowrap rounded-2xl border px-4 py-3 text-sm font-medium transition">
            {{ __('site.dashboard.nav.accounts') }}
        </a>
        <a href="{{ route('dashboard.payouts') }}" class="{{ request()->routeIs('dashboard.payouts') ? 'border-amber-400/30 bg-amber-400/12 text-white' : 'border-white/6 bg-white/2 text-slate-300 hover:bg-white/5 hover:text-white' }} shrink-0 whitespace-nowrap rounded-2xl border px-4 py-3 text-sm font-medium transition">
            {{ __('site.dashboard.nav.payouts') }}
        </a>
        <a href="{{ route('dashboard.settings') }}" class="{{ request()->routeIs('dashboard.settings') ? 'border-amber-400/30 bg-amber-400/12 text-white' : 'border-white/6 bg-white/2 text-slate-300 hover:bg-white/5 hover:text-white' }} shrink-0 whitespace-nowrap rounded-2xl border px-4 py-3 text-sm font-medium transition">
            {{ __('site.dashboard.nav.settings') }}
        </a>
    </nav>

    @if (! empty($primaryAccount))
        <div class="mt-6 hidden space-y-6 lg:block">
            <div class="surface-card rounded-3xl p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Primary account</p>
                <p class="mt-3 text-lg font-semibold text-white">{{ $primaryAccount['reference'] }}</p>
                <p class="mt-2 text-sm text-slate-400">{{ $primaryAccount['platform_account_id'] }}</p>

                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-400">Balance</dt>
                        <dd class="font-semibold text-white">{{ $primaryAccount['balance'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-400">Equity</dt>
                        <dd class="font-semibold text-white">{{ $primaryAccount['equity'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-400">Floating P&L</dt>
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
</aside>
