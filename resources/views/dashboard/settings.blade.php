@extends('layouts.dashboard')

@section('title', __('site.dashboard.settings_page.title').' | '.__('site.meta.brand'))
@section('dashboard-title', __('site.dashboard.settings_page.title'))
@section('dashboard-subtitle', __('site.dashboard.settings_page.subtitle'))

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
        <section class="surface-panel rounded-[2rem] p-6">
            <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.dashboard.settings.profile_title') }}</p>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-300">{{ __('site.checkout.full_name') }}</span>
                    <input type="text" readonly value="{{ $profile['name'] }}" class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none">
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-300">{{ __('site.checkout.email') }}</span>
                    <input type="email" readonly value="{{ $profile['email'] }}" class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none">
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-300">{{ __('site.dashboard.settings.language_label') }}</span>
                    <input type="text" readonly value="{{ $profile['language'] }}" class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none">
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-300">{{ __('site.dashboard.settings.timezone_label') }}</span>
                    <input type="text" readonly value="{{ $profile['timezone'] }}" class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none">
                </label>
            </div>
        </section>

        <section class="space-y-6">
            <div class="surface-card rounded-[2rem] p-6">
                <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.dashboard.settings.preferences_title') }}</p>
                <p class="mt-4 text-sm leading-7 text-slate-400">{{ __('site.dashboard.settings.preferences_copy') }}</p>
            </div>

            <div class="surface-card rounded-[2rem] p-6">
                <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.dashboard.settings.security_title') }}</p>
                <p class="mt-4 text-sm leading-7 text-slate-400">{{ __('site.dashboard.settings.security_copy') }}</p>
                <button type="button" disabled class="mt-6 w-full cursor-not-allowed rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-slate-400">
                    {{ __('site.dashboard.settings.save') }}
                </button>
            </div>
        </section>
    </div>
@endsection
