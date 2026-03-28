<button
    type="button"
    data-wolfi-launch
    class="assistant-fab assistant-fab-button"
    aria-label="{{ __('site.ai_assistant.floating_aria') }}"
    aria-controls="wolfi-modal"
    aria-expanded="false"
>
    <span class="assistant-fab-halo" aria-hidden="true"></span>
    <span class="assistant-fab-core" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.75a3.25 3.25 0 0 1 3.25 3.25v4a3.25 3.25 0 1 1-6.5 0V8A3.25 3.25 0 0 1 12 4.75Z" />
            <path stroke-linecap="round" d="M6.75 11.75a5.25 5.25 0 0 0 10.5 0" />
            <path stroke-linecap="round" d="M12 17v2.25" />
            <path stroke-linecap="round" d="M9.5 19.25h5" />
        </svg>
    </span>
    <span class="assistant-fab-ping" aria-hidden="true"></span>
    <span class="sr-only">{{ __('site.ai_assistant.floating_label') }}</span>
</button>

<div
    id="wolfi-modal"
    data-wolfi-modal
    class="wolfi-modal hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="wolfi-modal-title"
>
    <div class="wolfi-modal-backdrop" data-wolfi-close aria-hidden="true"></div>

    <div class="wolfi-modal-card">
        <div class="flex items-start justify-between gap-4">
            <div class="max-w-md">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('site.ai_assistant.name') }}</p>
                <h2 id="wolfi-modal-title" class="mt-3 text-2xl font-semibold text-white">{{ __('site.contact.voice_title') }}</h2>
                <p class="mt-3 text-sm leading-7 text-slate-300">{{ __('site.contact.voice_copy') }}</p>
            </div>

            <button
                type="button"
                data-wolfi-close
                aria-label="{{ __('site.ai_assistant.close_aria') }}"
                class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/4 text-slate-300 transition hover:border-white/20 hover:bg-white/8 hover:text-white"
            >
                <span class="text-xl leading-none">×</span>
            </button>
        </div>

        <div class="mt-6">
            @include('partials.voice-assistant-panel', [
                'assistantId' => 'wolfi-modal-assistant',
                'assistantClass' => 'wolfi-assistant-panel',
            ])
        </div>
    </div>
</div>
