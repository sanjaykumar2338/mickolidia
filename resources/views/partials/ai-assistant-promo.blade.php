@php
    $assistantQuestions = trans('site.ai_assistant.example_questions');
    $supportEmail = config('wolforix.support.email');
@endphp

<section id="site-ai-assistant" class="px-6 pb-14 pt-6 lg:px-8 lg:pb-18">
    <div class="mx-auto max-w-7xl">
        <div class="assistant-promo-panel relative overflow-hidden rounded-[2.6rem] px-6 py-8 sm:px-8 sm:py-10 lg:px-10 lg:py-12">
            <div class="assistant-promo-orb assistant-promo-orb-left" aria-hidden="true"></div>
            <div class="assistant-promo-orb assistant-promo-orb-right" aria-hidden="true"></div>

            <div class="relative z-10 grid gap-8 xl:grid-cols-[1fr_0.94fr] xl:items-center">
                <div class="max-w-3xl">
                    <span class="section-label">{{ __('site.ai_assistant.eyebrow') }}</span>
                    <h2 class="mt-5 max-w-3xl text-3xl font-semibold text-white sm:text-4xl lg:text-[3.1rem] lg:leading-[1.02]">
                        {{ __('site.ai_assistant.title') }}
                    </h2>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-slate-200">
                        {{ __('site.ai_assistant.description') }}
                    </p>

                    <div class="mt-4 inline-flex rounded-full border border-amber-400/24 bg-amber-400/10 px-4 py-2 text-sm font-medium text-amber-100">
                        {{ __('site.ai_assistant.multi_language') }}
                    </div>

                    <div class="mt-8 grid gap-3 sm:grid-cols-2">
                        @foreach (trans('site.ai_assistant.features') as $feature)
                            <div class="flex items-start gap-3 rounded-[1.4rem] border border-white/8 bg-white/[0.04] px-4 py-3 text-sm text-slate-200">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-amber-400/30 bg-amber-400/12 text-amber-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                                    </svg>
                                </span>
                                <span>{{ $feature }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <button type="button" data-wolfi-launch class="primary-cta rounded-full px-8 py-4 text-base font-semibold">
                            {{ __('site.ai_assistant.start_chat') }}
                        </button>
                    </div>
                </div>

                <div class="assistant-preview-shell relative">
                    <div class="assistant-preview-card rounded-[2rem] border border-white/10 bg-slate-950/88 p-5 shadow-[0_28px_70px_rgba(2,6,23,0.45)] backdrop-blur-xl sm:p-6">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.ai_assistant.preview_label') }}</p>
                                <p class="mt-2 text-xl font-semibold text-white">{{ __('site.ai_assistant.preview_title') }}</p>
                            </div>
                            <span class="rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-200">
                                {{ __('site.ai_assistant.preview_badge') }}
                            </span>
                        </div>

                        <p class="mt-4 text-sm leading-7 text-slate-400">{{ __('site.ai_assistant.preview_copy') }}</p>

                        <div class="mt-6 space-y-3">
                            @foreach ($assistantQuestions as $question)
                                <button
                                    type="button"
                                    data-wolfi-launch
                                    data-wolfi-question="{{ $question }}"
                                    class="assistant-question-link flex items-center justify-between gap-4 rounded-[1.4rem] px-4 py-3 text-left"
                                >
                                    <span class="text-sm font-medium text-slate-100">{{ $question }}</span>
                                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-amber-400/22 bg-amber-400/10 text-amber-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                                        </svg>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="assistant-support-strip relative z-10 mt-8 border-t border-white/8 pt-8 sm:mt-10 sm:pt-10">
                <div class="max-w-3xl">
                    <span class="section-label">{{ __('site.footer.contact_title') }}</span>
                    <p class="mt-4 text-sm leading-7 text-slate-300 sm:text-base">
                        {{ __('site.footer.contact_copy') }}
                    </p>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
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
