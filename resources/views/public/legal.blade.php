@extends('layouts.public')

@section('title', $page['title'].' | '.__('site.meta.brand'))

@section('content')
    <section class="px-6 pt-12 lg:px-8">
        <div class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="surface-panel rounded-[2rem] p-6 sm:p-8">
                <span class="section-label">{{ __('site.legal.eyebrow') }}</span>
                <h1 class="mt-5 text-4xl font-semibold text-white sm:text-5xl">{{ $page['title'] }}</h1>
                <p class="mt-5 max-w-3xl text-base leading-8 text-slate-300">{{ $page['intro'] }}</p>

                @if (! empty($page['highlight']))
                    <div class="mt-6 rounded-[1.8rem] border border-amber-400/18 bg-amber-400/10 p-6">
                        @if (! empty($page['highlight']['title']))
                            <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-200">{{ $page['highlight']['title'] }}</p>
                        @endif

                        @if (! empty($page['highlight']['items']))
                            <ul class="{{ ! empty($page['highlight']['title']) ? 'mt-4 ' : '' }}space-y-3 text-sm leading-7 text-amber-50">
                                @foreach ($page['highlight']['items'] as $item)
                                    <li class="rounded-2xl border border-amber-300/12 bg-black/15 px-4 py-3">{{ $item }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if (! empty($page['highlight']['note']))
                            <p class="mt-4 text-sm leading-7 text-amber-50/90">{{ $page['highlight']['note'] }}</p>
                        @endif
                    </div>
                @endif

                <div class="mt-10 space-y-8">
                    @foreach ($page['sections'] as $section)
                        <section class="rounded-[1.8rem] border border-white/8 bg-white/3 p-6">
                            <h2 class="text-2xl font-semibold text-white">{{ $section['title'] }}</h2>

                            @if (! empty($section['paragraphs']))
                                <div class="mt-4 space-y-4 text-sm leading-7 text-slate-300">
                                    @foreach ($section['paragraphs'] as $paragraph)
                                        <p>{{ $paragraph }}</p>
                                    @endforeach
                                </div>
                            @endif

                            @if (! empty($section['bullets']))
                                <ul class="mt-5 space-y-3 text-sm leading-7 text-slate-300">
                                    @foreach ($section['bullets'] as $bullet)
                                        <li class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">{{ $bullet }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                    @endforeach
                </div>
            </div>

            <aside class="space-y-5">
                <div class="surface-card rounded-[2rem] p-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.legal.quick_links') }}</p>
                    <nav class="mt-5 space-y-3">
                        @foreach (config('wolforix.legal_pages') as $slug => $configPage)
                            <a
                                href="{{ route($configPage['route_name']) }}"
                                class="{{ $pageSlug === $slug ? 'border-amber-400/25 bg-amber-400/10 text-white' : 'border-white/8 bg-white/3 text-slate-300 hover:bg-white/6 hover:text-white' }} block rounded-2xl border px-4 py-3 text-sm transition"
                            >
                                {{ __('site.legal.link_labels.'.$configPage['content_key']) }}
                            </a>
                        @endforeach
                    </nav>
                </div>

                <div class="rounded-[2rem] border border-sky-400/15 bg-sky-500/10 p-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.26em] text-sky-100">{{ __('site.legal.overview_title') }}</p>
                    <p class="mt-4 text-sm leading-7 text-sky-50/90">{{ __('site.legal.overview_copy') }}</p>
                </div>
            </aside>
        </div>
    </section>
@endsection
