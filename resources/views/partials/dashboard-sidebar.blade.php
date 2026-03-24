<aside class="border-b border-white/5 bg-slate-950/80 px-4 py-5 backdrop-blur-xl lg:border-b-0 lg:border-r lg:px-5">
    <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-3xl border border-white/6 bg-white/3 p-4 transition hover:border-amber-400/20 hover:bg-white/5">
        <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl border border-amber-400/18 bg-black/80">
            <img src="{{ asset('newfolder/IMG_8542.png') }}" alt="Wolforix" class="h-full w-full object-contain scale-105">
        </div>
        <div>
            <p class="inline-flex items-start text-sm font-semibold tracking-[0.28em] text-amber-300">
                <span>WOLFORIX</span>
                <span class="ml-1 text-[0.58em] leading-none tracking-normal text-amber-200">®</span>
            </p>
            <p class="mt-1 text-xs text-slate-400">{{ __('site.dashboard.sidebar_label') }}</p>
        </div>
    </a>

    <div class="mt-6 rounded-3xl border border-amber-400/15 bg-amber-400/10 px-4 py-3 text-sm text-amber-50">
        {{ __('site.dashboard.simulated_badge') }}
    </div>

    <nav class="mt-6 space-y-2">
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'border-amber-400/30 bg-amber-400/12 text-white' : 'border-white/6 bg-white/2 text-slate-300 hover:bg-white/5 hover:text-white' }} block rounded-2xl border px-4 py-3 text-sm font-medium transition">
            {{ __('site.dashboard.nav.overview') }}
        </a>
        <a href="{{ route('dashboard.accounts') }}" class="{{ request()->routeIs('dashboard.accounts') ? 'border-amber-400/30 bg-amber-400/12 text-white' : 'border-white/6 bg-white/2 text-slate-300 hover:bg-white/5 hover:text-white' }} block rounded-2xl border px-4 py-3 text-sm font-medium transition">
            {{ __('site.dashboard.nav.accounts') }}
        </a>
        <a href="{{ route('dashboard.payouts') }}" class="{{ request()->routeIs('dashboard.payouts') ? 'border-amber-400/30 bg-amber-400/12 text-white' : 'border-white/6 bg-white/2 text-slate-300 hover:bg-white/5 hover:text-white' }} block rounded-2xl border px-4 py-3 text-sm font-medium transition">
            {{ __('site.dashboard.nav.payouts') }}
        </a>
        <a href="{{ route('dashboard.settings') }}" class="{{ request()->routeIs('dashboard.settings') ? 'border-amber-400/30 bg-amber-400/12 text-white' : 'border-white/6 bg-white/2 text-slate-300 hover:bg-white/5 hover:text-white' }} block rounded-2xl border px-4 py-3 text-sm font-medium transition">
            {{ __('site.dashboard.nav.settings') }}
        </a>
    </nav>

    <div class="mt-6 surface-card rounded-3xl p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ __('site.dashboard.overview.rules_title') }}</p>
        <dl class="mt-4 space-y-3 text-sm">
            <div class="flex items-center justify-between gap-4">
                <dt class="text-slate-400">{{ __('site.dashboard.labels.target') }}</dt>
                <dd class="font-semibold text-white">8%</dd>
            </div>
            <div class="flex items-center justify-between gap-4">
                <dt class="text-slate-400">{{ __('site.dashboard.labels.daily_loss') }}</dt>
                <dd class="font-semibold text-white">5%</dd>
            </div>
            <div class="flex items-center justify-between gap-4">
                <dt class="text-slate-400">{{ __('site.dashboard.labels.max_loss') }}</dt>
                <dd class="font-semibold text-white">10%</dd>
            </div>
            <div class="flex items-center justify-between gap-4">
                <dt class="text-slate-400">{{ __('site.dashboard.labels.min_days') }}</dt>
                <dd class="font-semibold text-white">3</dd>
            </div>
        </dl>
    </div>
</aside>
