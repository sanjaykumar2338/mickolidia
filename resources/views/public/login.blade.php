@extends('layouts.public')

@section('title', __('site.auth.title').' | '.__('site.meta.brand'))

@section('content')
    <section class="px-6 pt-12 lg:px-8 lg:pt-16">
        <div class="mx-auto max-w-3xl">
            <div class="surface-panel rounded-[2.2rem] p-8 sm:p-10">
                <span class="section-label">{{ __('site.auth.eyebrow') }}</span>
                <h1 class="mt-6 text-4xl font-semibold text-white sm:text-5xl">{{ __('site.auth.title') }}</h1>
                <p class="mt-5 max-w-2xl text-base leading-8 text-slate-300">{{ __('site.auth.description') }}</p>

                <div class="mt-8 rounded-[1.8rem] border border-amber-400/18 bg-amber-400/10 p-5 text-sm leading-7 text-amber-50">
                    {{ __('site.auth.notice') }}
                </div>

                <div class="mt-8 flex flex-wrap gap-4">
                    <a href="{{ route('home') }}" class="primary-cta rounded-full px-8 py-4 text-base font-semibold">
                        {{ __('site.auth.primary_action') }}
                    </a>
                    <a href="{{ route('dashboard') }}" class="rounded-full border border-white/10 px-6 py-4 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                        {{ __('site.nav.dashboard_preview') }}
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
