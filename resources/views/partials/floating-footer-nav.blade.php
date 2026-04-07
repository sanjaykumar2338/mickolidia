@php
    $floatingNavLinks = [
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

<div class="floating-footer-nav lg:hidden" data-floating-footer-nav>
    <div data-floating-footer-nav-panel class="floating-footer-nav-panel hidden">
        <nav class="grid gap-2">
            @foreach ($floatingNavLinks as $link)
                <a
                    href="{{ $link['href'] }}"
                    data-floating-footer-nav-close
                    class="rounded-[1.1rem] border border-white/8 bg-white/[0.04] px-4 py-3 text-sm font-medium text-slate-100 transition hover:border-white/14 hover:bg-white/7 hover:text-white"
                >
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>
    </div>

    <button
        type="button"
        class="floating-footer-nav-button"
        data-floating-footer-nav-toggle
        data-open-label="{{ __('site.nav.menu_open') }}"
        data-close-label="{{ __('site.nav.menu_close') }}"
        aria-expanded="false"
        aria-label="{{ __('site.nav.menu_open') }}"
    >
        <svg data-floating-footer-nav-open-icon xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
            <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
        </svg>
        <svg data-floating-footer-nav-close-icon xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
            <path stroke-linecap="round" d="M6 6 18 18M18 6 6 18" />
        </svg>
        <span class="sr-only">{{ __('site.nav.menu_open') }}</span>
    </button>
</div>
