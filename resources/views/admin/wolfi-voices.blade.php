@extends('admin.layout')

@section('title', __('Wolfi Voices').' | '.__('site.meta.brand'))

@section('content')
    <div class="space-y-6">
        <section class="surface-panel rounded-[2rem] p-6 sm:p-7">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-300">{{ __('Internal admin') }}</p>
            <h1 class="mt-3 text-3xl font-semibold text-white sm:text-4xl">{{ __('Wolfi Voices') }}</h1>
            <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-400">
                {{ __('Manage the global Wolfi voice, preview language-aware options, and save one platform default for all users.') }}
            </p>
        </section>

        @include('partials.wolfi-voices-panel')
    </div>
@endsection
