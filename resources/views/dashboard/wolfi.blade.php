@extends('layouts.dashboard')

@section('title', __('site.dashboard.wolfi_hub_page.title').' | '.__('site.meta.brand'))
@section('dashboard-title', __('site.dashboard.wolfi_hub_page.title'))
@section('dashboard-subtitle', __('site.dashboard.wolfi_hub_page.subtitle'))

@section('content')
    <div class="space-y-6">
        @if (! empty($wolfiPanel))
            @include('dashboard.partials.wolfi-assistant', ['wolfiPanel' => $wolfiPanel])
        @else
            <section class="surface-panel rounded-[2rem] p-6 sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{{ __('site.dashboard.wolfi_hub_page.title') }}</p>
                <h2 class="mt-3 text-3xl font-semibold text-white">{{ __('site.dashboard.wolfi_hub_page.empty_title') }}</h2>
                <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-400">{{ __('site.dashboard.wolfi_hub_page.empty_copy') }}</p>
            </section>
        @endif
    </div>
@endsection
