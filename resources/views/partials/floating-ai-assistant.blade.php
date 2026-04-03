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
                src="{{ asset('newfolder/IMG_8542.png') }}"
                alt=""
                class="assistant-fab-avatar"
                loading="eager"
                decoding="async"
            >
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
            <div class="flex items-start gap-4">
                <div class="wolfi-avatar-shell shrink-0" data-wolfi-stage="avatar">
                    <video
                        data-wolfi-avatar-video
                        class="wolfi-avatar-video"
                        muted
                        loop
                        playsinline
                        preload="metadata"
                        poster="{{ asset('newfolder/IMG_8542.png') }}"
                        aria-hidden="true"
                        disablepictureinpicture
                    >
                        <source src="{{ asset('2136dfb8-85de-461a-9b2b-0d60c39ad04e.mp4') }}" type="video/mp4">
                    </video>
                </div>

                <div class="max-w-xl" data-wolfi-stage="intro">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('site.ai_assistant.name') }}</p>
                    <h2 id="wolfi-modal-title" class="mt-3 text-2xl font-semibold text-white sm:text-[2.1rem]">{{ __('site.contact.voice_title') }}</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300 sm:text-[15px]">{{ __('site.contact.voice_copy') }}</p>
                </div>
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

        <div class="mt-6" data-wolfi-stage="panel">
            @include('partials.voice-assistant-panel', [
                'assistantId' => 'wolfi-modal-assistant',
                'assistantClass' => 'wolfi-assistant-panel',
            ])
        </div>
    </div>
</div>
