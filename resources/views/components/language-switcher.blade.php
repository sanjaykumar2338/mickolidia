@props(['compact' => false, 'fullWidth' => false])

@php($currentLocale = app()->getLocale())
@php($supportedLocales = config('wolforix.supported_locales', []))
@php($fallbackLocale = config('wolforix.default_locale', 'en'))
@php($current = $supportedLocales[$currentLocale] ?? $supportedLocales[$fallbackLocale] ?? reset($supportedLocales))

<div data-locale-switcher @if($fullWidth) data-locale-full-width="true" @endif {{ $attributes->class(['relative z-[80]']) }}>
    <button
        type="button"
        data-locale-toggle
        aria-expanded="false"
        aria-label="{{ __('site.locale.current_label') }}: {{ $current['native'] ?? strtoupper($currentLocale) }}"
        class="{{ $compact ? 'gap-2 px-2.5 py-2' : 'gap-3 px-3 py-2' }} {{ $fullWidth ? 'w-full justify-between' : '' }} flex items-center rounded-full border border-white/8 bg-white/4 text-left text-sm text-white transition hover:border-amber-300/30 hover:bg-white/7"
    >
        <span class="{{ $compact ? 'h-8 w-8' : 'h-9 w-9' }} flex items-center justify-center overflow-hidden rounded-full border border-amber-400/16 bg-slate-950/80">
            @if (! empty($current['flag_asset']))
                <img src="{{ asset($current['flag_asset']) }}" alt="" aria-hidden="true" class="h-full w-full object-cover">
            @else
                <span class="text-base leading-none">{{ $current['flag'] ?? ($current['short'] ?? strtoupper($currentLocale)) }}</span>
            @endif
        </span>
        <span class="min-w-0 {{ $compact ? 'hidden sm:block' : 'block' }}">
            <span class="block text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('site.locale.current_label') }}</span>
            <span class="mt-0.5 block text-sm font-medium text-white">{{ $current['native'] }}</span>
        </span>
        <svg class="{{ $compact ? 'h-3.5 w-3.5' : 'h-4 w-4' }} text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0l-4.25-4.51a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
        </svg>
    </button>

    <div data-locale-menu class="pointer-events-auto fixed left-0 top-0 z-[9999] hidden w-[18rem] max-w-[calc(100vw-1.5rem)] overflow-y-auto rounded-[1.6rem] border border-white/8 bg-slate-950/96 p-3 shadow-2xl shadow-black/40 backdrop-blur-xl">
        <p class="px-3 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('site.locale.menu_title') }}</p>

        <div class="mt-1 space-y-1">
            @foreach (config('wolforix.supported_locales') as $code => $locale)
                <a
                    href="{{ route('locale.update', ['locale' => $code, 'redirect' => request()->fullUrl()]) }}"
                    data-locale-link
                    class="{{ $currentLocale === $code ? 'border-amber-300/25 bg-amber-400/12 text-white' : 'border-transparent text-slate-300 hover:border-white/8 hover:bg-white/5 hover:text-white' }} flex w-full items-center gap-3 rounded-2xl border px-3 py-3 text-left transition"
                >
                    <span class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border border-white/8 bg-slate-900/80">
                        @if (! empty($locale['flag_asset']))
                            <img src="{{ asset($locale['flag_asset']) }}" alt="" aria-hidden="true" class="h-full w-full object-cover">
                        @else
                            <span class="text-base leading-none">{{ $locale['flag'] ?? ($locale['short'] ?? strtoupper($code)) }}</span>
                        @endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium">{{ $locale['native'] }}</span>
                        <span class="mt-0.5 block text-xs uppercase tracking-[0.22em] text-slate-400">{{ $locale['short'] }}</span>
                    </span>
                </a>
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
