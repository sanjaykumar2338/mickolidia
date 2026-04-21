@php
    $wolfiFabImage = asset((string) config('wolfi.images.shortcut'));
@endphp

<button
    type="button"
    data-wolfi-launch
    class="assistant-fab assistant-fab-button"
    aria-label="{{ __('site.ai_assistant.floating_aria') }}"
    aria-controls="wolfi-modal"
    aria-expanded="false"
>
    <span class="assistant-fab-shimmer" aria-hidden="true"></span>
    <span class="assistant-fab-avatar-wrap" aria-hidden="true">
        <span class="assistant-fab-halo"></span>
        <span class="assistant-fab-orbit assistant-fab-orbit-left"></span>
        <span class="assistant-fab-orbit assistant-fab-orbit-right"></span>
        <span class="assistant-fab-core">
            <img
                src="{{ $wolfiFabImage }}"
                alt=""
                class="assistant-fab-avatar"
                loading="eager"
                decoding="async"
            >
            <video
                data-wolfi-fab-video
                class="assistant-fab-avatar-video"
                muted
                loop
                playsinline
                preload="metadata"
                poster="{{ $wolfiFabImage }}"
                aria-hidden="true"
                disablepictureinpicture
            >
                <source src="{{ asset('2136dfb8-85de-461a-9b2b-0d60c39ad04e.mp4') }}" type="video/mp4">
            </video>
        </span>
        <span class="assistant-fab-ping"></span>
    </span>
</button>

<div
    id="wolfi-modal"
    data-wolfi-modal
    class="wolfi-modal hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="wolfi-modal-title"
    aria-hidden="true"
>
    <div class="wolfi-modal-backdrop" data-wolfi-close aria-hidden="true"></div>

    <div class="wolfi-modal-card" data-wolfi-card>
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 max-w-xl" data-wolfi-stage="intro">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{{ __('site.contact.voice_title') }}</p>
                <h2 id="wolfi-modal-title" class="mt-3 text-2xl font-semibold text-white sm:text-[2.2rem]">{{ __('site.contact.voice_title') }}</h2>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300 sm:text-[15px]">{{ __('site.contact.voice_copy') }}</p>
            </div>
            <button
                type="button"
                data-wolfi-close
                aria-label="{{ __('site.ai_assistant.close_aria') }}"
                data-wolfi-stage="close"
                class="wolfi-modal-close inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/4 text-slate-300 transition hover:border-white/20 hover:bg-white/8 hover:text-white"
            >
                <span class="text-xl leading-none">×</span>
            </button>
        </div>

        <div class="wolfi-live-scene mt-6 overflow-hidden rounded-[2rem] border border-white/10" data-wolfi-stage="avatar" data-wolfi-live-scene>
            <div class="wolfi-live-scene-grid" aria-hidden="true"></div>
            <div class="wolfi-live-scene-glow wolfi-live-scene-glow-gold" aria-hidden="true"></div>
            <div class="wolfi-live-scene-glow wolfi-live-scene-glow-blue" aria-hidden="true"></div>

            <div class="relative z-10 flex flex-col items-center px-5 pb-6 pt-6 text-center sm:px-7 sm:pb-8 sm:pt-7">
                <div class="wolfi-live-avatar-wrap">
                    <span class="wolfi-live-wave wolfi-live-wave-left" aria-hidden="true"></span>
                    <span class="wolfi-live-wave wolfi-live-wave-right" aria-hidden="true"></span>

                    <div class="wolfi-live-avatar-shell wolfi-avatar-shell">
                        <span class="wolfi-live-avatar-ring wolfi-live-avatar-ring-outer" aria-hidden="true"></span>
                        <span class="wolfi-live-avatar-ring wolfi-live-avatar-ring-inner" aria-hidden="true"></span>
                        <img
                            src="{{ $wolfiFabImage }}"
                            alt="{{ __('site.contact.voice_title') }}"
                            class="wolfi-live-avatar-image wolfi-avatar-image"
                            loading="eager"
                            decoding="async"
                        >
                    </div>
                </div>

                <p class="wolfi-live-presence mt-6">
                    <span class="wolfi-live-presence-dot" aria-hidden="true"></span>
                    <span>{{ __('site.contact.voice_online') }}</span>
                </p>

                <h3
                    class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-[2.8rem]"
                    data-wolfi-hero-title
                    data-idle="{{ __('site.contact.voice_state_idle') }}"
                    data-listening="{{ __('site.contact.voice_state_listening') }}"
                    data-speaking="{{ __('site.contact.voice_state_speaking') }}"
                    data-rendering="{{ __('site.contact.voice_state_rendering') }}"
                >
                    {{ __('site.contact.voice_state_idle') }}
                </h3>

                <p
                    class="wolfi-live-status mt-3 max-w-xl text-base leading-7 text-slate-300"
                    data-wolfi-hero-status
                    data-default="{{ __('site.contact.voice_ready') }}"
                >
                    {{ __('site.contact.voice_ready') }}
                </p>

                <button
                    type="button"
                    class="wolfi-live-control mt-6 inline-flex items-center justify-center gap-3 rounded-full border border-amber-300/28 bg-amber-300/14 px-6 py-3.5 text-sm font-semibold text-amber-50 transition hover:border-amber-200/40 hover:bg-amber-300/20"
                    data-wolfi-talk-control
                    data-idle-label="{{ __('site.contact.voice_button') }}"
                    data-listening-label="{{ __('site.contact.voice_stop_button') }}"
                    data-speaking-label="{{ __('site.contact.voice_stop_play_button') }}"
                    data-rendering-label="{{ __('site.contact.voice_generating_audio') }}"
                >
                    <span class="wolfi-live-control-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v9m0 0a3 3 0 0 0 3-3V8a3 3 0 1 0-6 0v2a3 3 0 0 0 3 3Zm0 0v4m-4 0h8m-9 3h10" />
                        </svg>
                    </span>
                    <span data-wolfi-talk-control-label>{{ __('site.contact.voice_button') }}</span>
                </button>
            </div>
        </div>

        <div class="mt-6" data-wolfi-stage="panel">
            @include('partials.voice-assistant-panel', [
                'assistantId' => 'wolfi-modal-assistant',
                'assistantClass' => 'wolfi-assistant-panel',
            ])
        </div>
    </div>
</div>
