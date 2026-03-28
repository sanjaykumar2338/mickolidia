<div
    data-cookie-banner
    class="cookie-banner hidden"
>
    <div class="cookie-banner-card">
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-amber-300">{{ __('site.cookie.title') }}</p>
            <p class="mt-2 text-sm leading-7 text-slate-200">{{ __('site.cookie.message') }}</p>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-3">
            <a
                href="{{ route('privacy-policy') }}"
                class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6"
            >
                {{ __('site.cookie.learn_more') }}
            </a>
            <button
                type="button"
                data-cookie-banner-accept
                class="primary-cta rounded-full px-5 py-3 text-sm font-semibold"
            >
                {{ __('site.cookie.accept') }}
            </button>
        </div>
    </div>
</div>
