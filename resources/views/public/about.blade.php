@extends('layouts.public')

@section('title', __('site.home.about.title').' | '.__('site.meta.brand'))

@section('content')
    <section class="px-6 pt-12 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <div class="surface-panel relative overflow-hidden rounded-[2.4rem] p-6 sm:p-8 lg:p-10">
                <img src="{{ asset('newfolder/IMG_8542.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute -right-20 -top-14 h-64 w-64 opacity-[0.05]">

                <span class="section-label">{{ __('site.home.about.eyebrow') }}</span>

                <div class="mt-5 flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-3xl">
                        <h1 class="text-4xl font-semibold text-white sm:text-5xl">{{ __('site.home.about.title') }}</h1>
                        <p class="mt-5 text-base leading-8 text-slate-300">{{ __('site.home.about.intro') }}</p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('home').'#plans' }}" class="primary-cta rounded-full px-6 py-3 text-sm font-semibold">
                            {{ __('site.nav.plans') }}
                        </a>
                        <a href="{{ route('faq') }}" class="rounded-full border border-white/10 px-6 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                            {{ __('site.nav.faq') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="px-6 pb-12 pt-10 lg:px-8 lg:pb-16">
        <div class="mx-auto max-w-7xl">
            <div class="grid gap-8 xl:grid-cols-[0.86fr_1.14fr]">
                <div class="surface-panel relative overflow-hidden rounded-[2.4rem] p-6 sm:p-8">
                    <img src="{{ asset('newfolder/IMG_8542.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute -right-16 -top-10 h-56 w-56 opacity-[0.05]">

                    <div class="rounded-[1.9rem] border border-amber-400/20 bg-amber-400/10 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-200">{{ __('site.home.about.mission_label') }}</p>
                        <p class="mt-3 text-xl font-semibold leading-8 text-white">{{ __('site.home.about.mission') }}</p>
                    </div>

                    <div class="mt-8 flex flex-wrap gap-3">
                        @foreach (trans('site.home.about.pillars') as $pillar)
                            <span class="gold-pill rounded-full px-4 py-2 text-sm font-medium">{{ $pillar }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    @foreach (trans('site.home.about.blocks') as $block)
                        <article class="{{ $loop->last ? 'md:col-span-2' : '' }} surface-card rounded-[2rem] p-6">
                            <p class="text-lg font-semibold text-white">{{ $block['title'] }}</p>
                            <p class="mt-4 text-sm leading-7 text-slate-300">{{ $block['description'] }}</p>
                        </article>
                    @endforeach

                    <div class="about-emphasis rounded-[2rem] p-6 sm:p-7 md:col-span-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-200">{{ __('site.home.about.closing_label') }}</p>
                        <p class="mt-4 text-2xl font-semibold leading-tight text-white sm:text-3xl">{{ __('site.home.about.closing') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
