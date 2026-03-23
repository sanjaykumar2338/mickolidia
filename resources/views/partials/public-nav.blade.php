@php
    $navLinks = [
        [
            'href' => route('home'),
            'label' => __('site.nav.home'),
            'cta' => false,
        ],
        [
            'href' => route('home').'#plans',
            'label' => __('site.nav.plans'),
            'cta' => false,
        ],
        [
            'href' => route('faq'),
            'label' => __('site.nav.faq'),
            'cta' => false,
        ],
        [
            'href' => route('terms'),
            'label' => __('site.nav.legal'),
            'cta' => false,
        ],
        [
            'href' => route('dashboard'),
            'label' => __('site.nav.dashboard_preview'),
            'cta' => true,
        ],
    ];
@endphp

<header class="relative z-50 overflow-visible border-b border-white/5 bg-slate-950/72 backdrop-blur-xl">
    <div class="mx-auto max-w-7xl px-6 py-4 lg:px-8">
        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-2xl border border-amber-400/20 bg-black/70 shadow-lg shadow-amber-950/20">
                    <img src="{{ asset('branding/IMG_8365.jpeg') }}" alt="Wolforix" class="h-full w-full object-cover">
                </div>
                <div class="min-w-0">
                    <p class="inline-flex items-start text-sm font-semibold tracking-[0.28em] text-amber-300">
                        <span>WOLFORIX</span>
                        <span class="ml-1 text-[0.58em] leading-none tracking-normal text-amber-200">®</span>
                    </p>
                    <p class="max-w-[12rem] text-[11px] leading-4 text-slate-400 sm:max-w-none sm:text-xs">{{ __('site.public_layout.simulated_notice') }}</p>
                </div>
            </a>

            <div class="flex shrink-0 items-center gap-2 lg:hidden">
                <a href="{{ route('login') }}" class="rounded-full border border-white/10 bg-white/4 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/8">
                    {{ __('site.nav.login') }}
                </a>
                <x-language-switcher compact />
            </div>

            <div class="hidden items-center gap-3 text-sm text-slate-300 lg:flex">
                <nav class="flex flex-wrap items-center gap-2 lg:gap-3">
                    @foreach ($navLinks as $link)
                        <a
                            href="{{ $link['href'] }}"
                            class="{{ $link['cta'] ? 'rounded-full border border-amber-400/20 bg-amber-400/10 px-4 py-2 font-semibold text-amber-100 transition hover:border-amber-300/40 hover:bg-amber-400/15' : 'rounded-full px-3 py-2 transition hover:bg-white/5 hover:text-white' }}"
                        >
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
                <a href="{{ route('login') }}" class="rounded-full border border-white/10 bg-white/4 px-4 py-2 font-semibold text-white transition hover:border-white/20 hover:bg-white/8">
                    {{ __('site.nav.login') }}
                </a>
                <x-language-switcher compact />
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-slate-300 lg:hidden">
            <nav class="flex flex-wrap items-center gap-2">
                @foreach ($navLinks as $link)
                    <a
                        href="{{ $link['href'] }}"
                        class="{{ $link['cta'] ? 'rounded-full border border-amber-400/20 bg-amber-400/10 px-4 py-2 font-semibold text-amber-100 transition hover:border-amber-300/40 hover:bg-amber-400/15' : 'rounded-full px-3 py-2 transition hover:bg-white/5 hover:text-white' }}"
                    >
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </div>
</header>
