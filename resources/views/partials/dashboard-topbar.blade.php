<header class="border-b border-white/5 bg-slate-950/60 px-4 py-5 backdrop-blur-xl sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <span class="section-label">{{ __('site.public_layout.preview_badge') }}</span>
            <h1 class="mt-4 text-3xl font-semibold text-white">@yield('dashboard-title', __('site.dashboard.preview_title'))</h1>
            <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-400">@yield('dashboard-subtitle', __('site.dashboard.preview_subtitle'))</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="rounded-full border border-sky-400/20 bg-sky-500/10 px-4 py-2 text-sm text-sky-100">
                {{ __('site.dashboard.status_badge') }}
            </div>
            <x-language-switcher />
        </div>
    </div>
</header>
