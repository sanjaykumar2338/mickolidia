<div data-fixed-disclaimer class="pointer-events-none fixed inset-x-3 bottom-3 z-40 md:bottom-5">
    <div class="pointer-events-auto mx-auto flex max-w-6xl flex-col gap-3 rounded-[1.6rem] border border-white/8 bg-slate-950/92 px-4 py-3 shadow-2xl shadow-black/35 backdrop-blur-xl md:flex-row md:items-center md:justify-between md:px-5">
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.fixed_disclaimer.label') }}</p>
            <p class="mt-1 text-xs leading-6 text-slate-300 md:text-sm">{{ __('site.fixed_disclaimer.text') }}</p>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2 md:flex-nowrap">
            <a href="{{ route('faq') }}" class="rounded-full border border-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:border-white/20 hover:bg-white/6 md:text-[11px]">
                {{ __('site.fixed_disclaimer.faq_link') }}
            </a>
            <a href="{{ route('payout-policy') }}" class="rounded-full border border-amber-400/24 bg-amber-400/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-amber-50 transition hover:border-amber-300/35 hover:bg-amber-400/15 md:text-[11px]">
                {{ __('site.fixed_disclaimer.policy_link') }}
            </a>
            <button
                type="button"
                data-fixed-disclaimer-close
                aria-label="{{ __('site.fixed_disclaimer.close_label') }}"
                class="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/4 text-slate-300 transition hover:border-white/20 hover:bg-white/8 hover:text-white"
            >
                <span class="text-lg leading-none">×</span>
            </button>
        </div>
    </div>
</div>
