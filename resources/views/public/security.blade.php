@extends('layouts.public')

@section('title', __('site.security.meta_title').' | '.__('site.meta.brand'))

@php
    $securityIcons = [
        <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75c-1.94 1.24-4.47 1.88-7.5 1.88v5.25c0 4.96 3.11 8.1 7.5 9.37 4.39-1.27 7.5-4.41 7.5-9.37V5.63c-3.03 0-5.56-.64-7.5-1.88Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 11.25 1.5 1.5 3-3.75" />
        </svg>
        SVG,
        <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 12A4.5 4.5 0 0 1 12 7.5h7.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 4.5 19.5 7.5 16.5 10.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12A4.5 4.5 0 0 1 12 16.5H4.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 19.5 4.5 16.5 7.5 13.5" />
        </svg>
        SVG,
        <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 17.25h10.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 15.75 19.5 18l3.75-4.5" />
        </svg>
        SVG,
        <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9Z" />
        </svg>
        SVG,
    ];
@endphp

@section('content')
    <section class="px-6 pt-12 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <div class="surface-panel relative overflow-hidden rounded-[2.4rem] p-6 sm:p-8 lg:p-10">
                <img src="{{ asset('newfolder/IMG_8542.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute -right-20 -top-14 h-64 w-64 opacity-[0.05]">

                <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-3xl">
                        <span class="section-label">{{ __('site.security.eyebrow') }}</span>
                        <h1 class="mt-5 text-4xl font-semibold text-white sm:text-5xl">{{ __('site.security.title') }}</h1>
                        <p class="mt-5 text-base leading-8 text-slate-300">{{ __('site.security.description') }}</p>
                    </div>

                    <div class="max-w-sm">
                        <div class="gold-pill rounded-full px-4 py-2 text-sm font-medium">
                            {{ __('site.security.badge') }}
                        </div>
                        <p class="mt-4 text-sm leading-7 text-slate-400">{{ __('site.security.note') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="px-6 pb-12 pt-10 lg:px-8 lg:pb-16">
        <div class="mx-auto max-w-7xl">
            <div class="grid gap-5 lg:grid-cols-2">
                @foreach (trans('site.security.sections') as $section)
                    <article class="surface-card rounded-[2rem] p-6 sm:p-7">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-emerald-400/20 bg-emerald-500/10 text-emerald-100 shadow-[0_18px_40px_rgba(16,185,129,0.1)]">
                            {!! $securityIcons[$loop->index] ?? $securityIcons[0] !!}
                        </span>
                        <h2 class="mt-5 text-2xl font-semibold text-white">{{ $section['title'] }}</h2>
                        <p class="mt-4 text-sm leading-7 text-slate-300">{{ $section['description'] }}</p>

                        <ul class="mt-6 space-y-3">
                            @foreach ($section['items'] as $item)
                                <li class="flex items-start gap-3 rounded-[1.4rem] border border-white/8 bg-white/3 px-4 py-4 text-sm leading-7 text-slate-200">
                                    <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-emerald-300"></span>
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endsection
