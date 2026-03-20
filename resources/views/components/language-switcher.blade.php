@props(['compact' => false])

@php($currentLocale = app()->getLocale())

<div {{ $attributes->class(['flex flex-wrap items-center gap-2']) }}>
    @foreach (config('wolforix.supported_locales') as $code => $locale)
        <form method="POST" action="{{ route('locale.update', $code) }}">
            @csrf
            <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
            <button
                type="submit"
                class="{{ $currentLocale === $code ? 'border-amber-400/30 bg-amber-400/12 text-amber-50' : 'border-white/8 bg-white/3 text-slate-300 hover:border-white/20 hover:bg-white/6 hover:text-white' }} rounded-full border px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] transition"
            >
                {{ $compact ? $locale['short'] : $locale['native'] }}
            </button>
        </form>
    @endforeach
</div>
