@props(['compact' => false])

@php($currentLocale = app()->getLocale())
@php($supportedLocales = config('wolforix.supported_locales', []))
@php($fallbackLocale = config('wolforix.default_locale', 'en'))
@php($current = $supportedLocales[$currentLocale] ?? $supportedLocales[$fallbackLocale] ?? reset($supportedLocales))

<div data-locale-switcher {{ $attributes->class(['relative']) }}>
    <button
        type="button"
        data-locale-toggle
        aria-expanded="false"
        class="flex items-center gap-3 rounded-full border border-white/8 bg-white/4 px-3 py-2 text-left text-sm text-white transition hover:border-amber-300/30 hover:bg-white/7"
    >
        <span class="flex h-9 w-9 items-center justify-center rounded-full border border-amber-400/16 bg-slate-950/80 text-base">{{ $current['flag'] }}</span>
        <span class="min-w-0 {{ $compact ? 'hidden sm:block' : 'block' }}">
            <span class="block text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('site.locale.current_label') }}</span>
            <span class="mt-0.5 block text-sm font-medium text-white">{{ $current['native'] }}</span>
        </span>
        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0l-4.25-4.51a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
        </svg>
    </button>

    <div data-locale-menu class="absolute right-0 z-50 mt-3 hidden w-[18rem] rounded-[1.6rem] border border-white/8 bg-slate-950/96 p-3 shadow-2xl shadow-black/40 backdrop-blur-xl">
        <p class="px-3 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('site.locale.menu_title') }}</p>

        <div class="mt-1 space-y-1">
            @foreach (config('wolforix.supported_locales') as $code => $locale)
                <form method="POST" action="{{ route('locale.update', $code) }}">
                    @csrf
                    <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
                    <button
                        type="submit"
                        class="{{ $currentLocale === $code ? 'border-amber-300/25 bg-amber-400/12 text-white' : 'border-transparent text-slate-300 hover:border-white/8 hover:bg-white/5 hover:text-white' }} flex w-full items-center gap-3 rounded-2xl border px-3 py-3 text-left transition"
                    >
                        <span class="flex h-10 w-10 items-center justify-center rounded-full border border-white/8 bg-slate-900/80 text-base">{{ $locale['flag'] }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium">{{ $locale['native'] }}</span>
                            <span class="mt-0.5 block text-xs uppercase tracking-[0.22em] text-slate-400">{{ $locale['short'] }}</span>
                        </span>
                    </button>
                </form>
            @endforeach
        </div>

        <div class="mt-3 border-t border-white/6 pt-3">
            <p class="px-3 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">{{ __('site.locale.future_label') }}</p>
            <div class="mt-3 flex flex-wrap gap-2 px-3">
                @foreach (config('wolforix.future_locales', []) as $locale)
                    <span class="rounded-full border border-white/8 bg-white/4 px-3 py-1.5 text-xs text-slate-300">
                        {{ $locale['flag'] }} {{ $locale['native'] }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</div>
