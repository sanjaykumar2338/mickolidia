@php
    $footerNavLinks = [
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
            'label' => __('site.footer.contact_short'),
        ],
    ];
@endphp

<footer class="border-t border-white/5 bg-slate-950/80">
    <div class="mx-auto grid max-w-7xl gap-6 px-6 py-12 md:gap-8 lg:grid-cols-2 lg:items-start lg:px-8 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,0.72fr)_minmax(0,0.9fr)]">
        <div class="surface-card rounded-3xl p-6 lg:self-start xl:p-7">
            <h3 class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">{{ __('site.footer.security_title') }}</h3>
            <p class="mt-4 text-[13px] leading-6 text-slate-300">{{ __('site.footer.security_line') }}</p>
            <a
                href="{{ route('security') }}"
                class="mt-5 inline-flex rounded-full border border-white/10 bg-white/4 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/8"
            >
                {{ __('site.footer.security_link') }}
            </a>
            <p class="mt-5 text-xs leading-6 text-slate-500">
                {{ __('site.footer.simulated_notice') }}
            </p>
        </div>

        <div class="surface-card rounded-3xl p-6 lg:self-start xl:p-7">
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
    </div>

    <div class="border-t border-white/5">
        <div class="mx-auto max-w-7xl px-6 py-6 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(18rem,0.75fr)] lg:items-start">
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

                <div
                    class="surface-card rounded-[1.9rem] p-4 sm:p-5"
                    data-footer-panel
                >
                    <button
                        type="button"
                        data-footer-panel-toggle
                        data-open-label="{{ __('site.nav.menu_open') }}"
                        data-close-label="{{ __('site.nav.menu_close') }}"
                        aria-expanded="false"
                        aria-label="{{ __('site.nav.menu_open') }}"
                        class="flex w-full items-center justify-between gap-4 text-left"
                    >
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">{{ __('site.footer.quick_navigation_eyebrow') }}</p>
                            <p class="mt-2 text-sm font-semibold text-white">{{ __('site.footer.quick_navigation') }}</p>
                        </div>
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/10 bg-white/4 text-slate-200 transition hover:border-white/20 hover:bg-white/8 hover:text-white">
                            <svg data-footer-panel-open-icon xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                            </svg>
                            <svg data-footer-panel-close-icon xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <path stroke-linecap="round" d="M6 6 18 18M18 6 6 18" />
                            </svg>
                        </span>
                    </button>

                    <div data-footer-panel-content class="hidden pt-5">
                        <nav class="grid gap-2 sm:grid-cols-2">
                            @foreach ($footerNavLinks as $link)
                                <a
                                    href="{{ $link['href'] }}"
                                    class="rounded-[1.1rem] border border-white/6 bg-white/3 px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-white/12 hover:bg-white/5 hover:text-white"
                                >
                                    {{ $link['label'] }}
                                </a>
                            @endforeach
                        </nav>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-3 border-t border-white/5 pt-5 text-xs text-slate-500 lg:flex-row lg:items-center lg:justify-between">
                <p>&copy; {{ now()->year }} {{ __('site.meta.brand') }}®. {{ __('site.footer.copyright') }}</p>
                <p>{{ __('site.footer.company_location') }}</p>
            </div>
        </div>
    </div>
</footer>
