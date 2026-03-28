<footer class="border-t border-white/5 bg-slate-950/80">
    <div class="mx-auto grid max-w-7xl gap-8 px-6 py-12 lg:grid-cols-[1.2fr_0.8fr_0.8fr] lg:px-8">
        <div class="surface-panel rounded-3xl p-6">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center">
                    <img src="{{ asset('newfolder/IMG_8542.png') }}" alt="Wolforix" class="h-full w-full object-contain">
                </div>
                <div>
                    <p class="inline-flex items-start text-sm font-semibold tracking-[0.24em] text-slate-200">
                        <span>WOLFORIX</span>
                        <span class="ml-1 text-[0.58em] leading-none tracking-normal text-slate-400">®</span>
                    </p>
                    <p class="mt-2 text-[13px] leading-6 text-slate-500">{{ __('site.public_layout.simulated_notice') }}</p>
                </div>
            </div>
            <p class="mt-6 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">{{ __('site.footer.disclaimer_title') }}</p>
            <div class="mt-4 space-y-3 text-[13px] leading-6 text-slate-400">
                @foreach (trans('site.footer.legal_copy') as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
        </div>

        <div>
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

        <div class="surface-card rounded-3xl p-6">
            <h3 class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">{{ __('site.footer.contact_title') }}</h3>
            <p class="mt-4 text-[13px] leading-6 text-slate-400">{{ __('site.footer.contact_copy') }}</p>
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <a href="{{ route('contact') }}" class="rounded-full border border-white/10 bg-white/4 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/8">
                    {{ __('site.nav.contact') }}
                </a>
                <a href="mailto:{{ config('wolforix.support.email') }}" class="text-sm font-medium text-slate-300 transition hover:text-white">
                    {{ config('wolforix.support.email') }}
                </a>
                <button type="button" data-wolfi-launch class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-white/20 hover:bg-white/6">
                    {{ __('site.ai_assistant.name') }}
                </button>
            </div>
            <p class="mt-5 text-xs leading-6 text-slate-500">
                {{ __('site.footer.simulated_notice') }}
            </p>
        </div>
    </div>

    <div class="border-t border-white/5">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-6 py-5 text-xs text-slate-500 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <p>&copy; {{ now()->year }} {{ __('site.meta.brand') }}®. {{ __('site.footer.copyright') }}</p>
            <p>{{ __('site.footer.company_location') }}</p>
        </div>
    </div>
</footer>
