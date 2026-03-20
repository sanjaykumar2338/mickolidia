@extends('layouts.public')

@php use Illuminate\Support\Str; @endphp

@section('title', __('site.faq.title').' | '.__('site.meta.brand'))

@section('content')
    <section class="px-6 pt-12 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <span class="section-label">{{ __('site.faq.eyebrow') }}</span>
            <h1 class="mt-5 text-4xl font-semibold text-white sm:text-5xl">{{ __('site.faq.title') }}</h1>
            <p class="mt-4 max-w-3xl text-base leading-8 text-slate-300">{{ __('site.faq.description') }}</p>

            <div class="mt-10 surface-panel rounded-[2rem] p-4 sm:p-6">
                <label class="block">
                    <span class="mb-3 block text-sm font-medium text-slate-300">{{ __('site.faq.search_label') }}</span>
                    <input
                        data-faq-search
                        type="search"
                        class="w-full rounded-2xl border border-white/10 bg-white/4 px-5 py-4 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                        placeholder="{{ __('site.faq.search_placeholder') }}"
                    >
                </label>
            </div>

            <div data-faq-empty class="mt-8 hidden rounded-3xl border border-white/8 bg-white/4 px-6 py-5 text-sm text-slate-300">
                {{ __('site.faq.no_results') }}
            </div>

            <div class="mt-10 space-y-10">
                @foreach ($faqSections as $section)
                    <section data-faq-section>
                        <div class="flex items-center gap-4">
                            <div class="h-px flex-1 bg-gradient-to-r from-amber-400/40 to-transparent"></div>
                            <h2 class="text-xl font-semibold text-white">{{ $section['title'] }}</h2>
                            <div class="h-px flex-1 bg-gradient-to-r from-transparent to-sky-400/35"></div>
                        </div>

                        <div class="mt-5 space-y-4">
                            @foreach ($section['items'] as $item)
                                <details
                                    data-faq-item
                                    data-faq-text="{{ Str::lower($item['question'].' '.$item['answer']) }}"
                                    class="surface-card rounded-[1.8rem] p-5"
                                >
                                    <summary class="cursor-pointer list-none text-lg font-medium text-white">
                                        {{ $item['question'] }}
                                    </summary>
                                    <p class="mt-4 text-sm leading-7 text-slate-300">{{ $item['answer'] }}</p>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </section>
@endsection
