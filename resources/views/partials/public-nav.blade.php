<header class="sticky top-0 z-40 border-b border-white/5 bg-slate-950/70 backdrop-blur-xl">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-4 lg:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-2xl border border-amber-400/20 bg-black/70 shadow-lg shadow-amber-950/20">
                <img src="{{ asset('branding/IMG_8365.jpeg') }}" alt="Wolforix" class="h-full w-full object-cover">
            </div>
            <div>
                <p class="text-sm font-semibold tracking-[0.28em] text-amber-300">WOLFORIX</p>
                <p class="text-xs text-slate-400">{{ __('site.public_layout.simulated_notice') }}</p>
            </div>
        </a>

        <div class="flex flex-wrap items-center justify-end gap-3 text-sm text-slate-300">
            <nav class="flex flex-wrap items-center gap-2 lg:gap-3">
                <a href="{{ route('home') }}" class="rounded-full px-3 py-2 transition hover:bg-white/5 hover:text-white">{{ __('site.nav.home') }}</a>
                <a href="{{ route('home') }}#plans" class="rounded-full px-3 py-2 transition hover:bg-white/5 hover:text-white">{{ __('site.nav.plans') }}</a>
                <a href="{{ route('faq') }}" class="rounded-full px-3 py-2 transition hover:bg-white/5 hover:text-white">{{ __('site.nav.faq') }}</a>
                <a href="{{ route('terms') }}" class="rounded-full px-3 py-2 transition hover:bg-white/5 hover:text-white">{{ __('site.nav.legal') }}</a>
                <a href="{{ route('dashboard') }}" class="rounded-full border border-amber-400/20 bg-amber-400/10 px-4 py-2 font-semibold text-amber-100 transition hover:border-amber-300/40 hover:bg-amber-400/15">
                    {{ __('site.nav.dashboard_preview') }}
                </a>
            </nav>
            <x-language-switcher compact />
        </div>
    </div>
</header>
