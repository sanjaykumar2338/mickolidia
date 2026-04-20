@php
    $authUser = request()->user();
    $socialLinks = collect(config('wolforix.social_links', []))
        ->filter(fn (array $link): bool => filled($link['url'] ?? null))
        ->all();
    $socialIconSvgs = [
        'facebook' => <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M14 8.2V6.7c0-.74.48-.91.82-.91h2.09V2.2L14.03 2.2c-3.2 0-3.93 2.39-3.93 3.92v2.08H7.58v3.7h2.52V22h3.9V11.9h2.94l.39-3.7H14Z" />
            </svg>
        SVG,
        'instagram' => <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <rect x="4" y="4" width="16" height="16" rx="4.5" />
                <circle cx="12" cy="12" r="3.25" />
                <circle cx="16.75" cy="7.25" r="0.75" fill="currentColor" stroke="none" />
            </svg>
        SVG,
        'telegram' => <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.5 4.75 3.75 11.2c-.84.32-.8 1.52.07 1.78l4.3 1.28 1.62 4.73c.29.84 1.39.98 1.87.24l2.22-3.43 4.37 3.2c.74.54 1.8.13 1.96-.77l2.2-12.1c.16-.9-.99-1.58-1.86-1.38Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 14.1 8.35-5.45-5.9 7.05" />
            </svg>
        SVG,
        'x' => <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M13.87 10.47 21.16 2h-1.73l-6.33 7.35L8.04 2H2.2l7.65 11.12L2.2 22h1.73l6.68-7.75L15.95 22h5.84l-7.92-11.53Zm-2.36 2.74-.78-1.11L4.57 3.3H7.2l4.98 7.12.77 1.1 6.48 9.25h-2.64l-5.28-7.56Z" />
            </svg>
        SVG,
        'youtube' => <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M21.58 7.18a2.63 2.63 0 0 0-1.85-1.86C18.1 4.88 12 4.88 12 4.88s-6.1 0-7.73.44a2.63 2.63 0 0 0-1.85 1.86A27.35 27.35 0 0 0 2 12a27.35 27.35 0 0 0 .42 4.82 2.63 2.63 0 0 0 1.85 1.86c1.63.44 7.73.44 7.73.44s6.1 0 7.73-.44a2.63 2.63 0 0 0 1.85-1.86A27.35 27.35 0 0 0 22 12a27.35 27.35 0 0 0-.42-4.82ZM10 15.1V8.9l5.2 3.1-5.2 3.1Z" />
            </svg>
        SVG,
    ];
    $footerMobileNavLinks = [
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
        [
            'href' => route('contact'),
            'label' => __('site.nav.contact'),
        ],
    ];
@endphp

<footer class="border-t border-white/5 bg-slate-950/80">
    <div class="mx-auto max-w-7xl px-6 py-8 lg:px-8">
        <div class="ml-auto max-w-xl surface-card rounded-3xl p-6 xl:p-7">
            <div class="flex h-full flex-col">
                <h3 class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">{{ __('site.footer.legal_title') }}</h3>
                <div class="mt-5 space-y-3">
                    @foreach (config('wolforix.legal_pages') as $page)
                        <a
                            href="{{ route($page['route_name']) }}"
                            class="block rounded-2xl border border-white/6 bg-white/3 px-4 py-3 text-sm text-slate-400 transition hover:border-white/12 hover:bg-white/5 hover:text-white"
                        >
                            {{ __('site.legal.link_labels.'.$page['content_key']) }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($socialLinks !== [])
            <nav class="mt-5 flex items-center justify-center gap-3 rounded-[1.9rem] border border-white/8 bg-white/[0.03] px-4 py-4 shadow-[0_24px_70px_rgba(2,6,23,0.28)] lg:hidden" aria-label="Wolforix social links">
                @foreach ($socialLinks as $key => $link)
                    <a
                        href="{{ $link['url'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="{{ $link['label'] ?? str($key)->headline() }}"
                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/10 bg-slate-950/70 text-slate-100 shadow-[0_16px_40px_rgba(2,6,23,0.28)] transition hover:border-amber-300/35 hover:bg-amber-400/10 hover:text-amber-100"
                    >
                        {!! $socialIconSvgs[$key] ?? '<span class="text-sm font-semibold">'.e($link['label'] ?? str($key)->headline()).'</span>' !!}
                    </a>
                @endforeach
            </nav>
        @endif
    </div>

    <div class="border-t border-white/5">
        <div class="mx-auto max-w-7xl px-6 py-6 lg:px-8">
            <div class="grid gap-4">
                <div
                    class="surface-card rounded-[1.9rem] p-4 sm:p-5"
                    data-footer-panel
                >
                    <button
                        type="button"
                        data-footer-panel-toggle
                        class="flex w-full items-center justify-between gap-4 text-left"
                        aria-expanded="false"
                    >
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">{{ __('site.footer.disclaimer_title') }}</p>
                            <p class="mt-2 text-sm font-semibold text-white">{{ __('site.footer.view_full_legal_information') }}</p>
                        </div>
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/10 bg-white/4 text-slate-200 transition hover:border-white/20 hover:bg-white/8 hover:text-white">
                            <svg data-footer-panel-open-icon xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <path stroke-linecap="round" d="M4 12h16M12 4v16" />
                            </svg>
                            <svg data-footer-panel-close-icon xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <path stroke-linecap="round" d="M6 6 18 18M18 6 6 18" />
                            </svg>
                        </span>
                    </button>

                    <div data-footer-panel-content class="hidden pt-5">
                        <div class="rounded-[1.55rem] border border-white/8 bg-white/[0.03] p-5 sm:p-6">
                            <div class="space-y-4 text-base leading-8 text-slate-300 sm:text-[1.05rem] sm:leading-9">
                                @foreach (trans('site.footer.legal_copy') as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="mt-4 border-t border-white/5 pt-4">
                <div class="lg:hidden" data-mobile-nav data-mobile-nav-autoscroll>
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 space-y-3 text-xs text-slate-500">
                            <p>&copy; {{ now()->year }} {{ __('site.meta.brand') }}®. {{ __('site.footer.copyright') }}</p>
                            <p>{{ __('site.footer.company_location') }}</p>
                        </div>

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
                                @foreach ($footerMobileNavLinks as $link)
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

                <div class="hidden flex-col gap-3 text-xs text-slate-500 lg:flex lg:flex-row lg:items-center lg:justify-between">
                    <p>&copy; {{ now()->year }} {{ __('site.meta.brand') }}®. {{ __('site.footer.copyright') }}</p>
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        @if ($socialLinks !== [])
                            <nav class="flex flex-wrap items-center gap-2" aria-label="Wolforix social links">
                                @foreach ($socialLinks as $key => $link)
                                    <a
                                        href="{{ $link['url'] }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label="{{ $link['label'] ?? str($key)->headline() }}"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-white/[0.03] text-slate-300 transition hover:border-amber-300/30 hover:bg-amber-400/10 hover:text-amber-100"
                                    >
                                        {!! $socialIconSvgs[$key] ?? '<span class="text-xs font-semibold">'.e($link['label'] ?? str($key)->headline()).'</span>' !!}
                                    </a>
                                @endforeach
                            </nav>
                        @endif
                        <p>{{ __('site.footer.company_location') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
