@extends('layouts.public')

@section('title', __('site.contact.title').' | '.__('site.meta.brand'))

@section('content')
    <section class="px-6 pt-12 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <div class="surface-panel relative overflow-hidden rounded-[2.4rem] p-6 sm:p-8 lg:p-10">
                <div class="absolute inset-y-0 right-0 w-1/2 bg-[radial-gradient(circle_at_top_right,rgba(244,183,74,0.14),transparent_55%)]"></div>

                <span class="section-label">{{ __('site.contact.eyebrow') }}</span>

                <div class="mt-5 flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-3xl">
                        <h1 class="text-4xl font-semibold text-white sm:text-5xl">{{ __('site.contact.title') }}</h1>
                        <p class="mt-5 text-base leading-8 text-slate-300">{{ __('site.contact.description') }}</p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="mailto:{{ $supportEmail }}" class="primary-cta rounded-full px-6 py-3 text-sm font-semibold">
                            {{ __('site.contact.primary_action') }}
                        </a>
                        <a href="{{ route('faq') }}" class="rounded-full border border-white/10 px-6 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                            {{ __('site.contact.secondary_action') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="px-6 pt-10 lg:px-8">
        <div class="mx-auto grid max-w-7xl gap-6 xl:grid-cols-[0.92fr_1.08fr]">
            <article id="live-chat" class="surface-panel scroll-mt-28 rounded-[2rem] p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.contact.email_title') }}</p>
                <h2 class="mt-4 text-2xl font-semibold text-white">{{ $supportEmail }}</h2>
                <p class="mt-4 text-sm leading-7 text-slate-300">{{ __('site.contact.email_copy') }}</p>
                <div class="mt-5 rounded-[1.4rem] border border-white/8 bg-white/4 px-5 py-4 text-sm text-slate-300">
                    {{ __('site.contact.email_response', ['hours' => config('wolforix.support.business_hours')]) }}
                </div>
                <a href="mailto:{{ $supportEmail }}" class="mt-6 inline-flex rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                    {{ __('site.contact.email_button') }}
                </a>
            </article>

            <article class="surface-panel rounded-[2rem] p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.contact.live_chat_title') }}</p>
                <h2 class="mt-4 text-2xl font-semibold text-white">{{ __('site.contact.live_chat_title') }}</h2>
                <p class="mt-4 text-sm leading-7 text-slate-300">{{ __('site.contact.live_chat_copy') }}</p>
                <div class="mt-5 rounded-[1.4rem] border border-amber-400/18 bg-amber-400/10 px-5 py-4 text-sm text-amber-50">
                    {{ __('site.contact.live_chat_note') }}
                </div>
                <div class="mt-5">
                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.contact.live_chat_label') }}</span>
                        <textarea
                            data-contact-chat-message
                            rows="5"
                            class="w-full rounded-[1.4rem] border border-white/10 bg-white/4 px-4 py-4 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                            placeholder="{{ __('site.contact.live_chat_placeholder') }}"
                        ></textarea>
                    </label>
                    <p
                        data-contact-chat-status
                        data-empty-message="{{ __('site.contact.live_chat_empty') }}"
                        class="mt-3 text-sm text-slate-400"
                    >
                        {{ __('site.contact.live_chat_status') }}
                    </p>
                    <button
                        type="button"
                        data-contact-chat-launch
                        data-contact-chat-email="{{ $supportEmail }}"
                        data-contact-chat-subject="{{ __('site.contact.live_chat_subject') }}"
                        class="mt-5 inline-flex rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6"
                    >
                        {{ __('site.contact.live_chat_button') }}
                    </button>
                </div>
            </article>
        </div>
    </section>

    <section class="px-6 pb-12 pt-10 lg:px-8 lg:pb-16">
        <div class="mx-auto max-w-7xl">
            <div class="surface-panel rounded-[2.4rem] p-6 sm:p-8">
                <div class="grid gap-8 xl:grid-cols-[0.9fr_1.1fr]">
                    <div>
                        <span class="section-label">{{ __('site.contact.voice_title') }}</span>
                        <h2 class="mt-5 text-3xl font-semibold text-white">{{ __('site.contact.voice_title') }}</h2>
                        <p class="mt-4 text-base leading-8 text-slate-300">{{ __('site.contact.voice_copy') }}</p>
                        <a href="{{ route('faq') }}" class="mt-6 inline-flex rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                            {{ __('site.contact.voice_open_faq') }}
                        </a>
                    </div>

                    @include('partials.voice-assistant-panel', [
                        'assistantId' => 'voice-assistant',
                    ])
                </div>
            </div>
        </div>
    </section>
@endsection
