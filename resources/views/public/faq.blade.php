@extends('layouts.public')

@php
    use Illuminate\Support\Str;

    $faqSearchText = static function (array $item): string {
        $segments = [
            $item['question'] ?? '',
            $item['answer'] ?? '',
        ];

        foreach ($item['answer_paragraphs'] ?? [] as $paragraph) {
            $segments[] = $paragraph;
        }

        foreach ($item['answer_sections'] ?? [] as $section) {
            $segments[] = $section['title'] ?? '';

            foreach ($section['paragraphs'] ?? [] as $paragraph) {
                $segments[] = $paragraph;
            }

            foreach ($section['bullets'] ?? [] as $bullet) {
                $segments[] = $bullet;
            }
        }

        return Str::lower(implode(' ', array_filter($segments)));
    };
@endphp

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
                                    id="{{ Str::slug(($section['title'] ?? '').' '.($item['question'] ?? '')) }}"
                                    data-faq-item
                                    data-faq-text="{{ $faqSearchText($item) }}"
                                    class="surface-card rounded-[1.8rem] p-5"
                                >
                                    <summary class="cursor-pointer list-none text-lg font-medium text-white">
                                        {{ $item['question'] }}
                                    </summary>

                                    @if (! empty($item['answer']))
                                        <p class="mt-4 text-sm leading-7 text-slate-300">{{ $item['answer'] }}</p>
                                    @endif

                                    @if (! empty($item['answer_paragraphs']))
                                        <div class="mt-4 space-y-4 text-sm leading-7 text-slate-300">
                                            @foreach ($item['answer_paragraphs'] as $paragraph)
                                                <p>{{ $paragraph }}</p>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if (! empty($item['answer_sections']))
                                        <div class="mt-5 space-y-5">
                                            @foreach ($item['answer_sections'] as $sectionContent)
                                                <section class="rounded-2xl border border-white/6 bg-black/15 p-4">
                                                    <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-200">{{ $sectionContent['title'] }}</h3>

                                                    @if (! empty($sectionContent['paragraphs']))
                                                        <div class="mt-3 space-y-3 text-sm leading-7 text-slate-300">
                                                            @foreach ($sectionContent['paragraphs'] as $paragraph)
                                                                <p>{{ $paragraph }}</p>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                    @if (! empty($sectionContent['bullets']))
                                                        <ul class="mt-3 space-y-2 text-sm leading-7 text-slate-300">
                                                            @foreach ($sectionContent['bullets'] as $bullet)
                                                                <li class="rounded-xl border border-white/6 bg-white/3 px-3 py-2">{{ $bullet }}</li>
                                                            @endforeach
                                                        </ul>
                                                    @endif
                                                </section>
                                            @endforeach
                                        </div>
                                    @endif
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </section>
@endsection
