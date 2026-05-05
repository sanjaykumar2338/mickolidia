@php
    use Illuminate\Support\Facades\Lang;

    $assistantId = $assistantId ?? 'voice-assistant';
    $assistantQuestion = trim((string) ($assistantQuestion ?? request('assistant_question', '')));
    $assistantClass = $assistantClass ?? 'rounded-[2rem] border border-white/8 bg-white/4 p-5 sm:p-6';
    $assistantExampleQuestions = trans('site.ai_assistant.example_questions');
    $supportedVoiceLocales = array_keys(config('wolforix.supported_locales', []));
    $currentVoiceLocale = in_array(app()->getLocale(), $supportedVoiceLocales, true)
        ? app()->getLocale()
        : (string) config('wolforix.default_locale', 'en');
    $voiceLocales = [$currentVoiceLocale];
    $voiceLocaleMap = [
        'en' => 'en-US',
        'de' => 'de-DE',
        'es' => 'es-ES',
        'fr' => 'fr-FR',
        'hi' => 'hi-IN',
        'it' => 'it-IT',
        'pt' => 'pt-PT',
    ];
    $voicePlanSizes = collect(config('wolforix.challenge_models.one_step.pricing', []))
        ->keys()
        ->map(fn ($size) => ((int) $size / 1000).'K')
        ->implode(', ');
    $firstPayoutDays = (int) config('wolforix.challenge_models.one_step.funded.first_withdrawal_days', 21);
    $payoutCycleDays = (int) config('wolforix.challenge_models.one_step.funded.payout_cycle_days', 14);
    $voiceTtsAvailable = app(\App\Services\Voice\OpenAiTextToSpeechService::class)->isConfigured();

    $faqVoiceIndex = request()->routeIs('checkout.*')
        ? []
        : app(\App\Support\PublicContentIndex::class)->voiceAssistantIndex($voiceLocales, $voiceLocaleMap);

    foreach ($voiceLocales as $voiceLocale) {
        $faqVoiceIndex = [
            ...$faqVoiceIndex,
            [
                'locale' => $voiceLocale,
                'speech_locale' => $voiceLocaleMap[$voiceLocale] ?? strtoupper($voiceLocale),
                'section' => Lang::get('site.ai_assistant.name', [], $voiceLocale),
                'question' => Lang::get('site.contact.voice_input_placeholder', [], $voiceLocale),
                'answer' => Lang::get('site.contact.voice_payout_fallback', [
                    'first_payout_days' => $firstPayoutDays,
                    'payout_cycle_days' => $payoutCycleDays,
                ], $voiceLocale),
                'url' => route('payout-policy'),
                'search_text' => trim(implode(' ', array_filter([
                    Lang::get('site.contact.voice_input_placeholder', [], $voiceLocale),
                    Lang::get('site.contact.voice_payout_fallback', [
                        'first_payout_days' => $firstPayoutDays,
                        'payout_cycle_days' => $payoutCycleDays,
                    ], $voiceLocale),
                    'payout first payout first withdrawal retiro retirada auszahlung retrait payout cycle',
                ]))),
            ],
            [
                'locale' => $voiceLocale,
                'speech_locale' => $voiceLocaleMap[$voiceLocale] ?? strtoupper($voiceLocale),
                'section' => Lang::get('site.ai_assistant.name', [], $voiceLocale),
                'question' => Lang::get('site.ai_assistant.name', [], $voiceLocale),
                'answer' => Lang::get('site.contact.voice_plan_fallback', ['sizes' => $voicePlanSizes], $voiceLocale),
                'url' => route('home').'#plans',
                'search_text' => trim(implode(' ', array_filter([
                    Lang::get('site.contact.voice_plan_fallback', ['sizes' => $voicePlanSizes], $voiceLocale),
                    'plan challenge account size funded one step two step 5k 10k 25k 50k 100k',
                ]))),
            ],
            [
                'locale' => $voiceLocale,
                'speech_locale' => $voiceLocaleMap[$voiceLocale] ?? strtoupper($voiceLocale),
                'section' => Lang::get('site.ai_assistant.name', [], $voiceLocale),
                'question' => Lang::get('site.ai_assistant.name', [], $voiceLocale),
                'answer' => Lang::get('site.contact.voice_checkout_fallback', [], $voiceLocale),
                'url' => route('login'),
                'search_text' => trim(implode(' ', array_filter([
                    Lang::get('site.contact.voice_checkout_fallback', [], $voiceLocale),
                    'checkout login signup register sign in get plan auth account',
                ]))),
            ],
            [
                'locale' => $voiceLocale,
                'speech_locale' => $voiceLocaleMap[$voiceLocale] ?? strtoupper($voiceLocale),
                'section' => Lang::get('site.ai_assistant.name', [], $voiceLocale),
                'question' => Lang::get('site.ai_assistant.name', [], $voiceLocale),
                'answer' => Lang::get('site.contact.voice_discount_fallback', [], $voiceLocale),
                'url' => route('home'),
                'search_text' => trim(implode(' ', array_filter([
                    Lang::get('site.contact.voice_discount_fallback', [], $voiceLocale),
                    'discount promo promo code launch code get discount ignore rabat descuento remise',
                ]))),
            ],
            [
                'locale' => $voiceLocale,
                'speech_locale' => $voiceLocaleMap[$voiceLocale] ?? strtoupper($voiceLocale),
                'section' => Lang::get('site.ai_assistant.name', [], $voiceLocale),
                'question' => Lang::get('site.ai_assistant.name', [], $voiceLocale),
                'answer' => Lang::get('site.contact.voice_trial_fallback', [], $voiceLocale),
                'url' => route('trial.register'),
                'search_text' => trim(implode(' ', array_filter([
                    Lang::get('site.contact.voice_trial_fallback', [], $voiceLocale),
                    'free trial demo free demo demo account practice account trial register login email password existing user mt5 connector ea expert advisor base url account reference secret token connect mt5',
                ]))),
            ],
            [
                'locale' => $voiceLocale,
                'speech_locale' => $voiceLocaleMap[$voiceLocale] ?? strtoupper($voiceLocale),
                'section' => Lang::get('site.ai_assistant.name', [], $voiceLocale),
                'question' => Lang::get('site.ai_assistant.name', [], $voiceLocale),
                'answer' => Lang::get('site.contact.voice_rules_fallback', [], $voiceLocale),
                'url' => route('faq'),
                'search_text' => trim(implode(' ', array_filter([
                    Lang::get('site.contact.voice_rules_fallback', [], $voiceLocale),
                    'rules drawdown max loss daily loss consistency phase leverage challenge',
                ]))),
            ],
        ];
    }

    $voiceAssistantConfig = [
        'assistant_name' => __('site.ai_assistant.name'),
        'intro_title' => __('site.contact.voice_intro_title'),
        'intro_message' => __('site.contact.voice_intro_message'),
        'intro_blocked' => __('site.contact.voice_intro_blocked'),
        'clarify_title' => __('site.contact.voice_clarify_title'),
        'clarify_intro' => __('site.contact.voice_clarify_intro'),
        'example_questions' => is_array($assistantExampleQuestions) ? array_values($assistantExampleQuestions) : [],
        'plan_fallback' => __('site.contact.voice_plan_fallback', ['sizes' => $voicePlanSizes]),
        'payout_fallback' => __('site.contact.voice_payout_fallback', [
            'first_payout_days' => $firstPayoutDays,
            'payout_cycle_days' => $payoutCycleDays,
        ]),
        'max_drawdown_fallback' => __('site.contact.voice_max_drawdown_fallback'),
        'trial_fallback' => __('site.contact.voice_trial_fallback'),
        'rules_fallback' => __('site.contact.voice_rules_fallback'),
        'checkout_fallback' => __('site.contact.voice_checkout_fallback'),
        'discount_fallback' => __('site.contact.voice_discount_fallback'),
        'general_fallback' => __('site.contact.voice_general_fallback'),
        'support_fallback' => __('site.contact.voice_support_fallback', [
            'email' => config('wolforix.support.email'),
        ]),
        'tts_available' => $voiceTtsAvailable,
        'tts_endpoint' => route('assistant.speech', [], false),
    ];
@endphp

<div
    id="{{ $assistantId }}"
    data-voice-assistant
    data-page-locale="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-initial-question="{{ $assistantQuestion }}"
    class="wolfi-voice-assistant {{ $assistantClass }}"
>
    <script type="application/json" data-voice-assistant-index>@json($faqVoiceIndex)</script>
    <script type="application/json" data-voice-assistant-config>@json($voiceAssistantConfig)</script>

    <label class="block" data-voice-stage="input">
        <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.contact.voice_input_label') }}</span>
        <input
            data-voice-question
            type="search"
            value="{{ $assistantQuestion }}"
            class="wolfi-input w-full rounded-[1.4rem] border border-white/10 bg-slate-950/80 px-4 py-4 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
            placeholder="{{ __('site.contact.voice_input_placeholder') }}"
        >
    </label>

    <div class="wolfi-action-row mt-5 grid gap-3 sm:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)_minmax(0,1fr)]" data-voice-stage="controls">
        <button
            type="button"
            data-voice-play
            data-play-label="{{ __('site.contact.voice_play_button') }}"
            data-stop-label="{{ __('site.contact.voice_stop_play_button') }}"
            data-empty-message="{{ __('site.contact.voice_play_requires_answer') }}"
            data-speaking="{{ __('site.contact.voice_speaking') }}"
            data-generating="{{ __('site.contact.voice_generating_audio') }}"
            data-fallback-message="{{ __('site.contact.voice_external_fallback') }}"
            data-unavailable-message="{{ __('site.contact.voice_audio_unavailable') }}"
            class="primary-cta wolfi-control-button wolfi-control-play rounded-full px-6 py-3.5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-45"
        >
            {{ __('site.contact.voice_play_button') }}
        </button>
        <button
            type="button"
            data-voice-mic
            data-start-label="{{ __('site.contact.voice_button') }}"
            data-stop-label="{{ __('site.contact.voice_stop_button') }}"
            data-listening="{{ __('site.contact.voice_listening') }}"
            data-unsupported="{{ __('site.contact.voice_unsupported') }}"
            data-stopped="{{ __('site.contact.voice_stopped') }}"
            data-no-speech="{{ __('site.contact.voice_no_speech') }}"
            data-mic-blocked="{{ __('site.contact.voice_mic_blocked') }}"
            data-audio-capture="{{ __('site.contact.voice_audio_capture') }}"
            data-permission-checking="{{ __('site.contact.voice_permission_checking') }}"
            data-secure-context="{{ __('site.contact.voice_secure_context') }}"
            class="wolfi-control-button wolfi-control-secondary rounded-full border border-white/10 px-6 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6"
        >
            {{ __('site.contact.voice_button') }}
        </button>
        <button
            type="button"
            data-voice-submit
            class="wolfi-control-button wolfi-control-secondary rounded-full border border-white/10 px-6 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6"
        >
            {{ __('site.contact.voice_submit') }}
        </button>
    </div>

    <p
        data-voice-status
        data-ready-message="{{ __('site.contact.voice_ready') }}"
        data-no-match-message="{{ __('site.contact.voice_no_match') }}"
        class="wolfi-status mt-4 text-sm text-slate-400"
        data-voice-stage="status"
    >
        {{ __('site.contact.voice_ready') }}
    </p>

    <p class="mt-3 text-xs leading-6 text-slate-500" data-voice-stage="status-note">
        {{ __('site.contact.voice_ai_notice') }}
    </p>

    <div data-voice-answer class="wolfi-answer-card mt-5 rounded-[1.6rem] border border-white/8 bg-slate-950/80 p-5" data-voice-stage="answer">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('site.contact.voice_answer_title') }}</p>
        <p data-voice-answer-question class="wolfi-answer-question mt-3 text-lg font-semibold text-white break-words">{{ __('site.contact.voice_empty') }}</p>
        <p data-voice-answer-text class="wolfi-answer-text mt-3 text-sm leading-7 text-slate-300" aria-live="polite" aria-atomic="true"></p>
    </div>

    @if (is_array($assistantExampleQuestions) && $assistantExampleQuestions !== [])
        <div class="wolfi-suggestion-block mt-5" data-voice-suggestions data-voice-stage="suggestions">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">{{ __('site.contact.voice_suggestions_label') }}</p>
                    <p class="mt-2 text-sm leading-6 text-slate-400">{{ __('site.contact.voice_suggestions_copy') }}</p>
                </div>
                <span class="hidden rounded-full border border-amber-400/18 bg-amber-400/8 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-100 sm:inline-flex">
                    {{ __('site.ai_assistant.preview_badge') }}
                </span>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($assistantExampleQuestions as $question)
                    <button
                        type="button"
                        data-voice-suggestion
                        data-question="{{ $question }}"
                        class="assistant-question-link wolfi-suggestion-chip flex items-center justify-between gap-4 rounded-[1.4rem] px-4 py-3 text-left"
                        style="--wolfi-delay: {{ $loop->index * 70 }}ms;"
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
        </div>
    @endif
</div>
