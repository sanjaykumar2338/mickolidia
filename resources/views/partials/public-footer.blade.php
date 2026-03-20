<footer class="border-t border-white/5 bg-slate-950/80">
    <div class="mx-auto grid max-w-7xl gap-8 px-6 py-12 lg:grid-cols-[1.2fr_0.8fr_0.8fr] lg:px-8">
        <div class="surface-panel rounded-3xl p-6">
            <span class="section-label">{{ __('site.footer.disclaimer_title') }}</span>
            <h2 class="mt-5 text-2xl font-semibold text-white">{{ __('site.footer.company_title') }}</h2>
            <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300">{{ __('site.footer.summary') }}</p>
            <p class="mt-4 text-sm leading-7 text-slate-400">{{ __('site.footer.service_copy') }}</p>
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-300">{{ __('site.footer.legal_title') }}</h3>
            <div class="mt-5 space-y-3">
                @foreach (config('wolforix.legal_pages') as $page)
                    <a
                        href="{{ route($page['route_name']) }}"
                        class="block rounded-2xl border border-white/6 bg-white/3 px-4 py-3 text-sm text-slate-300 transition hover:border-amber-400/20 hover:bg-white/5 hover:text-white"
                    >
                        {{ __('site.legal.link_labels.'.$page['content_key']) }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="space-y-6">
            <div class="surface-card rounded-3xl p-6">
                <h3 class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-300">{{ __('site.footer.operations_title') }}</h3>
                <p class="mt-4 text-sm leading-7 text-slate-400">{{ __('site.footer.operations_copy') }}</p>
            </div>
            <div class="rounded-3xl border border-amber-400/20 bg-amber-400/10 p-6 text-sm leading-7 text-amber-50">
                {{ __('site.footer.simulated_notice') }}
            </div>
        </div>
    </div>

    <div class="border-t border-white/5">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-6 py-5 text-sm text-slate-500 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <p>&copy; {{ now()->year }} {{ __('site.meta.brand') }}. {{ __('site.footer.copyright') }}</p>
            <p>{{ __('site.footer.company_location') }}</p>
        </div>
    </div>
</footer>
