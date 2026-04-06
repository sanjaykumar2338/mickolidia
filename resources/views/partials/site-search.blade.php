@php
    $searchItems = app(\App\Support\PublicContentIndex::class)->siteSearchIndex(app()->getLocale());
@endphp

<div data-site-search class="fixed inset-0 z-[80] hidden items-start justify-center bg-slate-950/82 px-6 py-8 backdrop-blur-xl">
    <div class="flex max-h-[calc(100vh-4rem)] w-full max-w-3xl flex-col overflow-hidden rounded-[2rem] border border-white/10 bg-slate-950/95 shadow-[0_32px_90px_rgba(2,6,23,0.52)]">
        <div class="flex items-center justify-between gap-4 border-b border-white/8 px-6 py-5">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.search.title') }}</p>
                <p class="mt-2 text-sm text-slate-400">{{ __('site.search.description') }}</p>
            </div>
            <button
                type="button"
                data-site-search-close
                class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/4 text-white transition hover:border-white/20 hover:bg-white/8"
                aria-label="{{ __('site.search.close') }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <path stroke-linecap="round" d="M6 6 18 18M18 6 6 18" />
                </svg>
            </button>
        </div>

        <div class="search-scroll min-h-0 overflow-y-auto px-6 py-6">
            <form data-site-search-form>
                <label class="block">
                    <span class="sr-only">{{ __('site.search.title') }}</span>
                    <input
                        data-site-search-input
                        type="search"
                        placeholder="{{ __('site.search.placeholder') }}"
                        class="w-full rounded-[1.4rem] border border-white/10 bg-white/4 px-5 py-4 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                    >
                </label>
            </form>

            <div class="mt-6 flex items-center justify-between gap-4">
                <p
                    data-site-search-state
                    data-featured-title="{{ __('site.search.featured_title') }}"
                    data-results-one="{{ __('site.search.results_one') }}"
                    data-results-many="{{ __('site.search.results_many') }}"
                    class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400"
                >
                    {{ __('site.search.featured_title') }}
                </p>
            </div>

            <div data-site-search-empty class="mt-5 hidden rounded-[1.6rem] border border-white/8 bg-white/4 px-5 py-4 text-sm text-slate-300">
                {{ __('site.search.empty') }}
            </div>

            <div data-site-search-results class="mt-5 space-y-3"></div>
        </div>

        <script type="application/json" data-site-search-index>@json($searchItems)</script>
    </div>
</div>
