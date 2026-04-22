@php
    $assistantQuestions = trans('site.ai_assistant.example_questions');
    $supportEmail = config('wolforix.support.email');
    $homepageWolfiImage = asset((string) config('wolfi.images.homepage'));
    $homepageWolfiRightImage = asset((string) config('wolfi.images.homepage_right', config('wolfi.images.homepage')));
    $isHomepage = request()->routeIs('home');
@endphp

@if ($isHomepage)
    <section id="site-ai-assistant" class="px-4 pb-8 pt-0 sm:px-6 lg:px-8 lg:pb-12 lg:pt-4">
        <div class="mx-auto max-w-7xl">
            <div class="assistant-home-panel relative overflow-hidden rounded-[2.4rem] px-5 py-6 sm:px-8 sm:py-8 lg:px-10 lg:py-10">
                <div class="assistant-home-orb assistant-home-orb-left" aria-hidden="true"></div>
                <div class="assistant-home-orb assistant-home-orb-right" aria-hidden="true"></div>
                <div class="assistant-home-orb assistant-home-orb-bottom" aria-hidden="true"></div>

                <div class="assistant-home-grid relative z-10 grid gap-8 lg:grid-cols-[minmax(0,0.94fr)_minmax(19rem,0.76fr)] lg:items-center lg:gap-12">
                    <div class="assistant-home-copy max-w-3xl text-center lg:text-left">
                        <span class="section-label assistant-home-label justify-center lg:justify-start">{{ __('site.ai_assistant.eyebrow') }}</span>
                        <h2 class="assistant-home-brand mt-5">{{ __('site.ai_assistant.title') }}</h2>
                        <p class="assistant-home-tagline mx-auto mt-4 lg:mx-0">{{ __('site.ai_assistant.visual_title') }}</p>
                        <p class="assistant-home-headline mt-6">{{ __('site.ai_assistant.home_headline') }}</p>
                        <p class="assistant-home-description mx-auto mt-4 max-w-2xl lg:mx-0">
                            {{ __('site.ai_assistant.home_description') }}
                        </p>
                    </div>

                    <div class="assistant-home-visual">
                        <div class="assistant-home-visual-stack">
                            <div class="assistant-home-availability inline-flex items-center gap-3 rounded-full px-4 py-2.5">
                                <span class="assistant-home-availability-dot" aria-hidden="true"></span>
                                <span>{{ __('site.ai_assistant.multi_language') }}</span>
                            </div>

                            <div class="assistant-home-visual-card">
                                <div class="assistant-home-visual-frame">
                                    <div class="assistant-home-visual-glow" aria-hidden="true"></div>
                                    <div class="assistant-home-visual-image-wrap">
                                        <img
                                            src="{{ $homepageWolfiRightImage }}"
                                            alt="{{ __('site.ai_assistant.home_visual_alt') }}"
                                            class="assistant-home-visual-image"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                data-wolfi-launch
                                aria-controls="wolfi-modal"
                                aria-expanded="false"
                                class="assistant-home-cta assistant-home-cta-primary assistant-home-visual-cta"
                            >
                                <span>{{ __('site.ai_assistant.start_chat') }}</span>
                                <span class="assistant-home-cta-primary-icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@else
    <section id="site-ai-assistant" class="px-6 pb-6 pt-0 lg:px-8 lg:pb-10 lg:pt-4">
        <div class="mx-auto max-w-7xl">
            <div class="assistant-promo-panel relative overflow-hidden rounded-[2.6rem] px-6 py-7 sm:px-8 sm:py-8 lg:px-10 lg:py-10">
                <div class="assistant-promo-orb assistant-promo-orb-left" aria-hidden="true"></div>
                <div class="assistant-promo-orb assistant-promo-orb-right" aria-hidden="true"></div>

                <div class="relative z-10 grid gap-8 xl:grid-cols-[minmax(0,0.92fr)_minmax(20rem,0.88fr)] xl:items-center">
                    <div class="max-w-3xl">
                        <span class="section-label">{{ __('site.ai_assistant.eyebrow') }}</span>
                        <h2 class="mt-5 max-w-3xl text-4xl font-semibold text-white sm:text-5xl lg:text-[5.2rem] lg:leading-[0.92]">
                            {{ __('site.ai_assistant.title') }}
                        </h2>
                        <p class="mt-4 text-xs font-semibold uppercase tracking-[0.34em] text-slate-200 sm:text-sm">
                            {{ __('site.ai_assistant.visual_title') }}
                        </p>
                        <p class="mt-5 max-w-2xl text-base leading-8 text-slate-200">
                            {{ __('site.ai_assistant.description') }}
                        </p>

                        <div class="mt-4 inline-flex rounded-full border border-amber-400/24 bg-amber-400/10 px-4 py-2 text-sm font-medium text-amber-100">
                            {{ __('site.ai_assistant.multi_language') }}
                        </div>

                        <div class="mt-6 flex flex-wrap items-center gap-4">
                            <button type="button" data-wolfi-launch class="primary-cta rounded-full px-8 py-4 text-base font-semibold">
                                {{ __('site.ai_assistant.start_chat') }}
                            </button>
                            <button type="button" data-wolfi-launch class="inline-flex items-center justify-center rounded-full border border-white/10 px-8 py-4 text-base font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                                {{ __('site.ai_assistant.floating_cta') }}
                            </button>
                        </div>

                        @if (is_array($assistantQuestions) && $assistantQuestions !== [])
                            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                @foreach ($assistantQuestions as $question)
                                    <button
                                        type="button"
                                        data-wolfi-launch
                                        data-wolfi-question="{{ $question }}"
                                        class="assistant-question-link flex items-center justify-between gap-4 rounded-[1.4rem] px-4 py-3 text-left"
                                    >
                                        <span class="min-w-0 text-sm font-medium text-slate-100">{{ $question }}</span>
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-amber-400/22 bg-amber-400/10 text-amber-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                                            </svg>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="assistant-preview-shell">
                        <div class="assistant-preview-card assistant-mascot-card overflow-hidden rounded-[2rem] p-4 shadow-[0_28px_70px_rgba(2,6,23,0.45)] backdrop-blur-xl sm:p-5">
                            <span class="assistant-promo-stat assistant-promo-stat-top">{{ __('site.ai_assistant.visual_title') }}</span>

                            <div class="assistant-mascot-stage">
                                <div class="assistant-mascot-visual assistant-mascot-visual-home">
                                    <span class="assistant-core-breath assistant-core-breath-back" aria-hidden="true"></span>

                                    <div class="assistant-portrait-frame assistant-portrait-frame-home">
                                        <img
                                            src="{{ $homepageWolfiImage }}"
                                            alt="{{ __('site.ai_assistant.visual_alt') }}"
                                            class="assistant-portrait-image assistant-portrait-image-home"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    </div>

                                    <span class="assistant-core-breath assistant-core-breath-front" aria-hidden="true"></span>
                                </div>
                            </div>

                            <div class="rounded-[1.7rem] border border-white/10 bg-slate-950/72 p-5 sm:p-6">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">{{ __('site.ai_assistant.visual_response_label') }}</p>
                                <p class="mt-3 text-xl font-semibold text-white sm:text-[1.55rem]">{{ __('site.ai_assistant.visual_response_preview') }}</p>
                                <p class="mt-3 text-sm leading-7 text-slate-300">{{ __('site.ai_assistant.visual_copy') }}</p>
                                <p class="mt-4 text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('site.ai_assistant.visual_cta_hint') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="assistant-support-strip relative z-10 mt-6 border-t border-white/8 pt-6 sm:mt-8 sm:pt-8">
                    <div class="max-w-3xl">
                        <span class="section-label">{{ __('site.footer.contact_title') }}</span>
                        <p class="mt-4 text-sm leading-7 text-slate-300 sm:text-base">
                            {{ __('site.footer.contact_copy') }}
                        </p>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <a href="mailto:{{ $supportEmail }}" class="assistant-support-card group rounded-[1.6rem] px-5 py-5 sm:px-6">
                            <div class="flex items-start gap-4">
                                <span class="assistant-support-icon inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-amber-400/20 bg-amber-400/10 text-amber-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5 12 13l8-5.5" />
                                        <rect x="3" y="5" width="18" height="14" rx="2.5" />
                                    </svg>
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">{{ __('site.contact.email_title') }}</p>
                                    <p class="mt-3 text-xl font-semibold text-white">{{ $supportEmail }}</p>
                                    <p class="mt-3 text-sm leading-7 text-slate-300">{{ __('site.contact.email_copy') }}</p>
                                    <span class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-amber-100 transition group-hover:text-white">
                                        {{ __('site.contact.email_button') }}
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </a>

                        <a href="{{ route('contact').'#live-chat' }}" class="assistant-support-card group rounded-[1.6rem] px-5 py-5 sm:px-6">
                            <div class="flex items-start gap-4">
                                <span class="assistant-support-icon inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-sky-400/18 bg-sky-400/10 text-sky-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 19.5 4.8 21a.4.4 0 0 1-.63-.33V6.75A2.75 2.75 0 0 1 6.92 4h10.16a2.75 2.75 0 0 1 2.75 2.75v8.5A2.75 2.75 0 0 1 17.08 18H8.52L7 19.5Z" />
                                    </svg>
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200">{{ __('site.contact.live_chat_title') }}</p>
                                    <p class="mt-3 text-xl font-semibold text-white">{{ __('site.contact.live_chat_button') }}</p>
                                    <p class="mt-3 text-sm leading-7 text-slate-300">{{ __('site.contact.live_chat_note') }}</p>
                                    <span class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-sky-100 transition group-hover:text-white">
                                        {{ __('site.contact.live_chat_button') }}
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif
