@php
    $authUser = request()->user();
    $aboutMenu = [
        [
            'href' => route('about'),
            'label' => __('site.nav.about_us'),
        ],
        [
            'href' => route('security'),
            'label' => __('site.nav.security'),
        ],
        [
            'href' => route('contact'),
            'label' => __('site.nav.contact'),
        ],
    ];

    $navLinks = [
        [
            'href' => route('home').'#plans',
            'label' => __('site.nav.plans'),
            'cta' => false,
        ],
        [
            'href' => route('news'),
            'label' => __('site.nav.news'),
            'cta' => false,
        ],
        [
            'href' => route('faq'),
            'label' => __('site.nav.faq'),
            'cta' => false,
        ],
        [
            'label' => __('site.nav.about'),
            'cta' => false,
            'children' => $aboutMenu,
        ],
    ];

    $mobileNavLinks = [
        [
            'href' => route('home').'#plans',
            'label' => __('site.nav.plans'),
        ],
        [
            'href' => route('news'),
            'label' => __('site.nav.news'),
        ],
        [
            'href' => route('faq'),
            'label' => __('site.nav.faq'),
        ],
        [
            'href' => route('about'),
            'label' => __('site.nav.about'),
        ],
        [
            'href' => route('security'),
            'label' => __('site.nav.security'),
        ],
    ];

@endphp

<header class="relative z-50 overflow-visible border-b border-white/5 bg-slate-950/72 backdrop-blur-xl lg:sticky lg:top-0">
    <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8 lg:py-4">
        <div class="lg:hidden" data-mobile-nav>
            <div class="flex items-start justify-between gap-3">
                <a href="{{ route('home') }}" class="flex min-w-0 flex-1 items-center gap-3 pr-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center">
                        <img src="{{ asset('IMG_8543.png') }}" alt="Wolforix" class="h-full w-full object-contain">
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="inline-flex items-start text-[0.78rem] font-semibold tracking-[0.24em] text-amber-300">
                            <span>WOLFORIX</span>
                            <span class="ml-1 text-[0.58em] leading-none tracking-normal text-amber-200">®</span>
                        </p>
                        <p class="mt-1 max-w-[12rem] text-[11px] leading-4 text-slate-400">{{ __('site.public_layout.simulated_notice') }}</p>
                    </div>
                </a>

                <button
                    type="button"
                    data-mobile-nav-toggle
                    data-open-label="{{ __('site.nav.menu_open') }}"
                    data-close-label="{{ __('site.nav.menu_close') }}"
                    aria-expanded="false"
                    aria-label="{{ __('site.nav.menu_open') }}"
                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/10 bg-white/4 text-slate-200 transition hover:border-white/20 hover:bg-white/8 hover:text-white"
                >
                    <svg data-mobile-nav-open-icon xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                    <svg data-mobile-nav-close-icon xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path stroke-linecap="round" d="M6 6 18 18M18 6 6 18" />
                    </svg>
                </button>
            </div>

            <div data-mobile-nav-panel class="hidden pt-4">
                <div class="rounded-[1.6rem] border border-white/8 bg-white/[0.03] p-3 shadow-[0_24px_60px_rgba(2,6,23,0.34)]">
                    <div class="grid gap-2">
                        <button
                            type="button"
                            data-site-search-open
                            data-mobile-nav-close
                            aria-label="{{ __('site.nav.search_aria') }}"
                            class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-full border border-white/10 bg-white/4 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/8"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <circle cx="11" cy="11" r="6.5" />
                                <path stroke-linecap="round" d="m16 16 4.5 4.5" />
                            </svg>
                            <span>{{ __('site.nav.search') }}</span>
                        </button>

                        <x-language-switcher compact full-width />
                    </div>

                    <nav class="mt-3 grid gap-2 border-t border-white/8 pt-3">
                        @foreach ($mobileNavLinks as $link)
                            <a
                                href="{{ $link['href'] }}"
                                data-mobile-nav-close
                                class="rounded-[1.15rem] border border-transparent px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-white/8 hover:bg-white/5 hover:text-white"
                            >
                                {{ $link['label'] }}
                            </a>
                        @endforeach

                        @if ($authUser)
                            <a
                                href="{{ route('dashboard') }}"
                                data-mobile-nav-close
                                class="mt-1 inline-flex min-h-11 items-center justify-center rounded-full border border-amber-400/24 bg-amber-400/10 px-4 py-2.5 text-sm font-semibold text-amber-100 transition hover:border-amber-300/40 hover:bg-amber-400/15"
                            >
                                {{ __('site.nav.dashboard') }}
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-full border border-white/10 bg-white/4 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/8">
                                    {{ __('site.nav.logout') }}
                                </button>
                            </form>
                        @else
                            <a
                                href="{{ route('login') }}"
                                data-mobile-nav-close
                                class="mt-1 inline-flex min-h-11 items-center justify-center rounded-full border border-white/10 bg-white/4 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/8"
                            >
                                {{ __('site.nav.login') }}
                            </a>
                        @endif
                    </nav>
                </div>
            </div>
        </div>

        <div class="hidden items-center justify-between gap-4 lg:flex">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center sm:h-14 sm:w-14">
                    <img src="{{ asset('IMG_8543.png') }}" alt="Wolforix" class="h-full w-full object-contain">
                </span>
                <div class="min-w-0">
                    <p class="inline-flex items-start text-[0.78rem] font-semibold tracking-[0.26em] text-amber-300 sm:text-sm sm:tracking-[0.28em]">
                        <span>WOLFORIX</span>
                        <span class="ml-1 text-[0.58em] leading-none tracking-normal text-amber-200">®</span>
                    </p>
                    <p class="max-w-[10.5rem] text-[10px] leading-4 text-slate-400 sm:max-w-none sm:text-xs">{{ __('site.public_layout.simulated_notice') }}</p>
                </div>
            </a>

            <div class="flex items-center gap-3 text-sm text-slate-300">
                <nav class="flex flex-wrap items-center gap-2 lg:gap-3">
                    @foreach ($navLinks as $link)
                        @if (! empty($link['children']))
                            <div class="group relative">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full px-3 py-2 transition hover:bg-white/5 hover:text-white"
                                >
                                    <span>{{ $link['label'] }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition group-hover:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                    </svg>
                                </button>
                                <div class="pointer-events-none absolute left-0 top-full z-40 pt-3 opacity-0 translate-y-2 transition duration-200 group-hover:pointer-events-auto group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:pointer-events-auto group-focus-within:translate-y-0 group-focus-within:opacity-100">
                                    <div class="w-56 rounded-[1.5rem] border border-white/10 bg-slate-950/95 p-2 shadow-[0_24px_60px_rgba(2,6,23,0.42)] backdrop-blur-xl">
                                        @foreach ($link['children'] as $child)
                                            <a
                                                href="{{ $child['href'] }}"
                                                class="block rounded-[1rem] px-4 py-3 text-sm text-slate-200 transition hover:bg-white/6 hover:text-white"
                                            >
                                                {{ $child['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @else
                            <a
                                href="{{ $link['href'] }}"
                                class="{{ $link['cta'] ? 'rounded-full border border-amber-400/20 bg-amber-400/10 px-4 py-2 font-semibold text-amber-100 transition hover:border-amber-300/40 hover:bg-amber-400/15' : 'rounded-full px-3 py-2 transition hover:bg-white/5 hover:text-white' }}"
                            >
                                {{ $link['label'] }}
                            </a>
                        @endif
                    @endforeach
                </nav>
                @if ($authUser)
                    <a href="{{ route('dashboard') }}" class="rounded-full border border-amber-400/20 bg-amber-400/10 px-4 py-2 font-semibold text-amber-100 transition hover:border-amber-300/40 hover:bg-amber-400/15">
                        {{ __('site.nav.dashboard') }}
                    </a>
                @endif
                @if ($authUser)
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-full border border-white/10 bg-white/4 px-4 py-2 font-semibold text-white transition hover:border-white/20 hover:bg-white/8">
                            {{ __('site.nav.logout') }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="rounded-full border border-white/10 bg-white/4 px-4 py-2 font-semibold text-white transition hover:border-white/20 hover:bg-white/8">
                        {{ __('site.nav.login') }}
                    </a>
                @endif
                <button
                    type="button"
                    data-site-search-open
                    aria-label="{{ __('site.nav.search_aria') }}"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/4 text-white transition hover:border-white/20 hover:bg-white/8"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5" />
                        <path stroke-linecap="round" d="m16 16 4.5 4.5" />
                    </svg>
                </button>
                <x-language-switcher compact />
            </div>
        </div>
    </div>
</header>
