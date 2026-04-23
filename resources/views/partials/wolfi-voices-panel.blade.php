<div class="space-y-6">
    @if (($showVoicePageFlash ?? true) && session('status'))
        <div class="rounded-[1.8rem] border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm leading-7 text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    @if (($showVoicePageFlash ?? true) && session('error'))
        <div class="rounded-[1.8rem] border border-rose-400/20 bg-rose-500/10 px-5 py-4 text-sm leading-7 text-rose-100">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-[1.8rem] border border-rose-400/20 bg-rose-500/10 px-5 py-4 text-sm leading-7 text-rose-100">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="surface-panel rounded-[2rem] p-6 sm:p-7">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-300">{{ __('Wolfi voice control') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-white sm:text-3xl">{{ __('Select Wolfi Voice') }}</h2>
                <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-400">
                    {{ __('Use one shared phrase to compare voices fairly. After saving, this voice becomes the default for Wolfi speech generation.') }}
                </p>
                <p class="mt-2 max-w-3xl text-xs leading-6 text-slate-500">
                    {{ __('Voice preview and playback automatically follow the website language you select.') }}
                </p>
            </div>
            <div class="rounded-[1.4rem] border border-amber-400/18 bg-amber-400/10 px-4 py-3 text-sm text-amber-100">
                <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-amber-300/90">{{ __('Current voice') }}</p>
                <p class="mt-2 text-base font-semibold text-white">{{ $selectedVoice['name'] ?? ucfirst($selectedVoiceId) }}</p>
                <p class="mt-1 text-xs uppercase tracking-[0.16em] text-amber-200/90">{{ $selectedVoice['id'] ?? $selectedVoiceId }}</p>
                @if (! empty($selectedVoice['provider_label']))
                    <p class="mt-1 text-xs text-amber-100/90">{{ $selectedVoice['provider_label'] }}</p>
                @endif
            </div>
        </div>

        <div class="mt-6 rounded-[1.5rem] border border-white/10 bg-black/20 px-5 py-4">
            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Preview phrase') }}</p>
            <p class="mt-3 text-sm leading-7 text-slate-200">
                "{{ $voiceSampleText }}"
            </p>
        </div>
    </section>

    <form
        method="POST"
        action="{{ $voiceUpdateEndpoint ?? route('dashboard.wolfi.voices.update') }}"
        class="space-y-6"
        data-wolfi-voice-lab
        data-preview-url="{{ $voicePreviewEndpoint }}"
        data-preview-text="{{ $voiceSampleText }}"
        data-preview-unavailable-message="{{ __('site.contact.voice_audio_unavailable') }}"
        data-preview-ready-message="{{ __('Preview ready. Click Play Preview on any voice card.') }}"
        data-preview-generating-message="{{ __('Generating preview audio...') }}"
        data-preview-session-message="{{ __('Your admin session expired. Please sign in to admin again and retry preview.') }}"
        data-preview-autoplay-message="{{ __('Audio playback was blocked by the browser. Click Play Preview again to allow sound.') }}"
        data-preview-playing-template="{{ __('Playing :voice preview...', ['voice' => '__VOICE__']) }}"
        data-preview-browser-fallback-message="{{ __('Provider preview unavailable. Using browser voice preview instead.') }}"
        data-preview-browser-unavailable-message="{{ __('Browser voice preview is unavailable on this device/browser.') }}"
    >
        @csrf

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($voiceOptions as $voice)
                @php
                    $voiceId = (string) ($voice['id'] ?? '');
                    $isSelected = $selectedVoiceId === $voiceId;
                @endphp

                <article
                    data-wolfi-voice-card
                    class="{{ $isSelected ? 'border-amber-400/35 bg-amber-400/10 shadow-[0_20px_60px_rgba(251,191,36,0.14)]' : 'border-white/10 bg-slate-950/72' }} rounded-[1.7rem] border p-5 transition"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="text-xl font-semibold text-white">{{ $voice['name'] }}</h3>
                            @if (! empty($voice['label']))
                                <p class="mt-2 text-sm font-medium text-amber-200">{{ $voice['label'] }}</p>
                            @endif
                        </div>
                        <span
                            data-wolfi-selected-indicator
                            class="{{ $isSelected ? '' : 'hidden' }} rounded-full border border-amber-300/25 bg-amber-300/15 px-3 py-1 text-[0.64rem] font-semibold uppercase tracking-[0.18em] text-amber-100"
                        >
                            {{ __('Selected') }}
                        </span>
                    </div>

                    @if (! empty($voice['description']))
                        <p class="mt-4 text-sm leading-7 text-slate-300">{{ $voice['description'] }}</p>
                    @endif

                    <p class="mt-4 text-[0.66rem] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Voice ID') }}: {{ $voiceId }}</p>
                    @if (! empty($voice['provider_label']))
                        <p class="mt-2 text-[0.66rem] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Provider') }}: {{ $voice['provider_label'] }}</p>
                    @endif

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-white/10 bg-white/6 px-3 py-2 text-sm text-white">
                            <input
                                type="radio"
                                name="voice_id"
                                value="{{ $voiceId }}"
                                {{ $isSelected ? 'checked' : '' }}
                                class="h-4 w-4 border-white/20 bg-black/30 text-amber-300 focus:ring-amber-300/40"
                                data-wolfi-voice-choice
                            >
                            <span>{{ __('Use this voice') }}</span>
                        </label>

                        <button
                            type="button"
                            data-wolfi-voice-preview
                            data-voice-id="{{ $voiceId }}"
                            data-play-label="{{ __('Play Preview') }}"
                            data-stop-label="{{ __('Stop Preview') }}"
                            class="inline-flex items-center justify-center rounded-full border border-amber-300/28 bg-amber-300/12 px-4 py-2 text-sm font-semibold text-amber-50 transition hover:border-amber-200/40 hover:bg-amber-300/20 disabled:cursor-not-allowed disabled:opacity-45"
                        >
                            {{ __('Play Preview') }}
                        </button>
                    </div>
                </article>
            @endforeach
        </section>

        <div
            class="{{ $ttsConfigured ? 'border-sky-400/20 bg-sky-500/10 text-sky-100' : 'border-rose-400/20 bg-rose-500/10 text-rose-100' }} rounded-[1.4rem] border px-4 py-3 text-sm"
            data-wolfi-voice-status
        >
            {{ $ttsConfigured ? __('Preview ready. Click Play Preview on any voice card.') : __('Cloud preview is not configured for the selected voice. The page will automatically fall back to browser-native voice preview.') }}
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button
                type="submit"
                class="inline-flex items-center justify-center rounded-full border border-amber-300/30 bg-amber-300/15 px-6 py-3 text-sm font-semibold text-amber-50 transition hover:border-amber-200/45 hover:bg-amber-300/24"
            >
                {{ __('Save Selected Voice') }}
            </button>

            <a
                href="{{ $voiceBackEndpoint ?? route('dashboard.wolfi') }}"
                class="inline-flex items-center justify-center rounded-full border border-white/12 bg-white/6 px-6 py-3 text-sm font-semibold text-slate-200 transition hover:bg-white/10 hover:text-white"
            >
                {{ $voiceBackLabel ?? __('Back to Wolfi Hub') }}
            </a>
        </div>
    </form>
</div>
