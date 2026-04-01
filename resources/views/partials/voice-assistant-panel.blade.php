@php
    use Illuminate\Support\Facades\Lang;
    use Illuminate\Support\Str;

    $assistantId = $assistantId ?? 'voice-assistant';
    $assistantQuestion = trim((string) ($assistantQuestion ?? request('assistant_question', '')));
    $assistantClass = $assistantClass ?? 'rounded-[2rem] border border-white/8 bg-white/4 p-5';
    $voiceLocales = array_keys(config('wolforix.supported_locales', []));
    $voiceLocaleMap = [
        'en' => 'en-US',
        'de' => 'de-DE',
        'es' => 'es-ES',
        'fr' => 'fr-FR',
    ];
    $voicePlanSizes = collect(config('wolforix.challenge_models.one_step.pricing', []))
        ->keys()
        ->map(fn ($size) => ((int) $size / 1000).'K')
        ->implode(', ');
    $firstPayoutDays = (int) config('wolforix.challenge_models.one_step.funded.first_withdrawal_days', 7);
    $payoutCycleDays = (int) config('wolforix.challenge_models.one_step.funded.payout_cycle_days', 14);
    $faqVoiceIndex = [];

    foreach ($voiceLocales as $voiceLocale) {
        $localizedFaqSections = Lang::get('site.faq.sections', [], $voiceLocale);

        if (! is_array($localizedFaqSections)) {
            continue;
        }

        foreach ($localizedFaqSections as $section) {
            foreach ($section['items'] ?? [] as $item) {
                if (! isset($item['question'])) {
                    continue;
                }

                $answerSegments = [
                    $item['answer'] ?? '',
                ];

                foreach ($item['answer_paragraphs'] ?? [] as $paragraph) {
                    $answerSegments[] = $paragraph;
                }

                foreach ($item['answer_sections'] ?? [] as $answerSection) {
                    $answerSegments[] = $answerSection['title'] ?? '';

                    foreach ($answerSection['paragraphs'] ?? [] as $paragraph) {
                        $answerSegments[] = $paragraph;
                    }

                    foreach ($answerSection['bullets'] ?? [] as $bullet) {
                        $answerSegments[] = $bullet;
                    }
                }

                $faqVoiceIndex[] = [
                    'locale' => $voiceLocale,
                    'speech_locale' => $voiceLocaleMap[$voiceLocale] ?? strtoupper($voiceLocale),
                    'section' => $section['title'] ?? '',
                    'question' => $item['question'],
                    'answer' => Str::limit(trim(implode(' ', array_filter($answerSegments))), 420),
                    'url' => route('faq'),
                    'search_text' => trim(implode(' ', array_filter([
                        $item['question'],
                        ...$answerSegments,
                    ]))),
                ];
            }
        }

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
        'plan_fallback' => __('site.contact.voice_plan_fallback', ['sizes' => $voicePlanSizes]),
        'payout_fallback' => __('site.contact.voice_payout_fallback', [
            'first_payout_days' => $firstPayoutDays,
            'payout_cycle_days' => $payoutCycleDays,
        ]),
        'rules_fallback' => __('site.contact.voice_rules_fallback'),
        'checkout_fallback' => __('site.contact.voice_checkout_fallback'),
        'discount_fallback' => __('site.contact.voice_discount_fallback'),
        'support_fallback' => __('site.contact.voice_support_fallback', [
            'email' => config('wolforix.support.email'),
        ]),
    ];
@endphp

<div
    id="{{ $assistantId }}"
    data-voice-assistant
    data-page-locale="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-initial-question="{{ $assistantQuestion }}"
    class="{{ $assistantClass }}"
>
    <script type="application/json" data-voice-assistant-index>@json($faqVoiceIndex)</script>
    <script type="application/json" data-voice-assistant-config>@json($voiceAssistantConfig)</script>

    <label class="block">
        <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.contact.voice_input_label') }}</span>
        <input
            data-voice-question
            type="search"
            value="{{ $assistantQuestion }}"
            class="w-full rounded-[1.4rem] border border-white/10 bg-slate-950/80 px-4 py-4 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
            placeholder="{{ __('site.contact.voice_input_placeholder') }}"
        >
    </label>

    <div class="mt-4 flex flex-wrap gap-3">
        <button
            type="button"
            data-voice-submit
            class="primary-cta rounded-full px-6 py-3 text-sm font-semibold"
        >
            {{ __('site.contact.voice_submit') }}
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
            class="rounded-full border border-white/10 px-6 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6"
        >
            {{ __('site.contact.voice_button') }}
        </button>
        <button
            type="button"
            data-voice-play
            data-play-label="{{ __('site.contact.voice_play_button') }}"
            data-stop-label="{{ __('site.contact.voice_stop_play_button') }}"
            data-empty-message="{{ __('site.contact.voice_play_requires_answer') }}"
            class="rounded-full border border-white/10 px-6 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6 disabled:cursor-not-allowed disabled:opacity-45"
        >
            {{ __('site.contact.voice_play_button') }}
        </button>
    </div>

    <p
        data-voice-status
        data-ready-message="{{ __('site.contact.voice_ready') }}"
        data-no-match-message="{{ __('site.contact.voice_no_match') }}"
        class="mt-4 text-sm text-slate-400"
    >
        {{ __('site.contact.voice_ready') }}
    </p>

    <div data-voice-answer class="mt-5 rounded-[1.6rem] border border-white/8 bg-slate-950/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('site.contact.voice_answer_title') }}</p>
        <p data-voice-answer-question class="mt-3 text-lg font-semibold text-white">{{ __('site.contact.voice_empty') }}</p>
        <p data-voice-answer-text class="mt-3 text-sm leading-7 text-slate-300"></p>
    </div>
</div>
