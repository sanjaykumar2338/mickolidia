@php
    $assistant = $wolfiPanel['assistant'] ?? [];
    $voice = $wolfiPanel['voice'] ?? [];
    $welcome = $wolfiPanel['welcome'] ?? [];
    $quickActions = $wolfiPanel['quick_actions'] ?? [];
    $pillars = $wolfiPanel['pillars'] ?? [];
    $smartInsightsMeta = $wolfiPanel['smart_insights'] ?? [];
    $smartInsights = $wolfiPanel['insights'] ?? [];
    $config = [
        'endpoint' => $wolfiPanel['endpoint'] ?? '',
        'page' => $wolfiPanel['page']['key'] ?? 'dashboard',
        'account_id' => $wolfiPanel['account_id'] ?? null,
        'assistant_name' => $assistant['name'] ?? 'Wolfi',
        'status_idle' => $assistant['status_idle'] ?? 'Ready to guide your next step',
        'status_thinking' => $assistant['status_thinking'] ?? 'Wolfi is reviewing your dashboard context',
        'status_error' => $assistant['status_error'] ?? 'Wolfi hit a temporary issue. Please try again.',
        'submit_label' => $assistant['submit_label'] ?? 'Ask Wolfi',
        'voice_label' => $voice['action_label'] ?? ($assistant['voice_label'] ?? 'Voice actions soon'),
        'voice_note' => $voice['action_note'] ?? ($assistant['voice_copy'] ?? ''),
    ];
    $messageStats = $welcome['stats'] ?? [];
    $messageBullets = $welcome['bullets'] ?? [];
    $promptButtonsDataAttribute = 'data-wolfi-prompt';
@endphp

<section
    class="wolfi-dashboard-shell surface-panel relative overflow-hidden rounded-[2.2rem] p-5 sm:p-6 lg:p-7"
    data-wolfi-dashboard
>
    <script type="application/json" data-wolfi-dashboard-config>@json($config)</script>

    <div class="pointer-events-none absolute inset-0">
        <div class="wolfi-dashboard-ambient wolfi-dashboard-ambient-gold"></div>
        <div class="wolfi-dashboard-ambient wolfi-dashboard-ambient-blue"></div>
        <div class="wolfi-dashboard-grid"></div>
    </div>

    <div class="relative grid gap-6 xl:grid-cols-[minmax(0,0.98fr)_minmax(0,1.02fr)] xl:items-start">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full border border-amber-300/20 bg-amber-300/10 px-3 py-1 text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-amber-100">
                    {{ $assistant['eyebrow'] ?? 'Wolfi Assistant' }}
                </span>
                <span class="rounded-full border border-white/10 bg-white/6 px-3 py-1 text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-200">
                    {{ $wolfiPanel['page']['title'] ?? __('Dashboard') }}
                </span>
            </div>

            <h2 class="mt-4 max-w-3xl text-3xl font-semibold text-white sm:text-[2.5rem]">
                {{ $assistant['title'] ?? 'Wolfi lives inside your trading workspace' }}
            </h2>
            <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-300 sm:text-[15px]">
                {{ $assistant['description'] ?? '' }}
            </p>

            <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(15rem,19rem)_minmax(0,1fr)]">
                <div class="wolfi-dashboard-avatar-card rounded-[1.9rem] border border-white/10 bg-black/20 p-5">
                    <div class="wolfi-dashboard-avatar-shell mx-auto" data-wolfi-avatar>
                        <span class="wolfi-dashboard-avatar-ring wolfi-dashboard-avatar-ring-outer"></span>
                        <span class="wolfi-dashboard-avatar-ring wolfi-dashboard-avatar-ring-inner"></span>
                        <img
                            src="{{ asset($assistant['avatar_asset'] ?? 'newfolder/IMG_8542.png') }}"
                            alt="{{ $assistant['name'] ?? 'Wolfi' }}"
                            class="wolfi-dashboard-avatar-image"
                            loading="eager"
                            decoding="async"
                        >
                    </div>

                    <div class="mt-5 text-center">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-amber-300">
                            {{ $assistant['response_label'] ?? 'Live response' }}
                        </p>
                        <p class="mt-3 text-lg font-semibold text-white" data-wolfi-live-label>
                            {{ $assistant['status_idle'] ?? 'Ready to guide your next step' }}
                        </p>
                        <p class="mt-3 text-sm leading-6 text-slate-400">
                            {{ $assistant['response_hint'] ?? '' }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($pillars as $pillar)
                        <article class="wolfi-dashboard-source-card rounded-[1.6rem] border border-white/8 bg-black/18 p-4">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-amber-300">{{ $pillar['title'] }}</p>
                            <p class="mt-3 text-sm leading-6 text-slate-300">{{ $pillar['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="wolfi-dashboard-chat-wrap min-w-0 rounded-[2rem] border border-white/10 bg-slate-950/72 p-4 sm:p-5 lg:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-amber-300">
                        {{ $assistant['sources_title'] ?? 'Grounded in Wolforix data' }}
                    </p>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-400">
                        {{ $assistant['sources_copy'] ?? '' }}
                    </p>
                </div>

                <button
                    type="button"
                    disabled
                    class="wolfi-dashboard-voice-slot inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-semibold text-slate-200"
                    title="{{ $voice['action_note'] ?? ($assistant['voice_copy'] ?? '') }}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v9m0 0a3 3 0 0 0 3-3V8a3 3 0 1 0-6 0v2a3 3 0 0 0 3 3Zm0 0v4m-4 0h8m-9 3h10" />
                    </svg>
                    <span>{{ $voice['action_label'] ?? ($assistant['voice_label'] ?? 'Voice actions soon') }}</span>
                </button>
            </div>

            @if ($smartInsights !== [])
                <section class="wolfi-dashboard-insights-block mt-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-amber-300">
                                {{ $smartInsightsMeta['title'] ?? 'Smart Insights' }}
                            </p>
                            <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-400">
                                {{ $smartInsightsMeta['description'] ?? '' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 xl:grid-cols-2">
                        @foreach ($smartInsights as $insight)
                            <button
                                type="button"
                                {{ $promptButtonsDataAttribute }}="{{ $insight['prompt'] }}"
                                class="wolfi-dashboard-insight-card wolfi-dashboard-insight-card-{{ $insight['tone'] ?? 'slate' }} text-left"
                            >
                                <div class="flex items-start gap-3">
                                    <span class="wolfi-dashboard-insight-icon" aria-hidden="true">{{ $insight['icon'] ?? '•' }}</span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-white">{{ $insight['label'] ?? 'Insight' }}</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-300">{{ $insight['message'] ?? '' }}</p>
                                        @if (! empty($insight['meta']))
                                            <p class="mt-3 text-xs leading-5 text-slate-500">{{ $insight['meta'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($quickActions !== [])
                <div class="mt-5 flex gap-3 overflow-x-auto pb-1" data-wolfi-suggestions>
                    @foreach ($quickActions as $action)
                        <button
                            type="button"
                            {{ $promptButtonsDataAttribute }}="{{ $action['prompt'] }}"
                            class="wolfi-dashboard-chip shrink-0 rounded-full border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-amber-300/30 hover:bg-amber-300/12"
                        >
                            {{ $action['label'] }}
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="wolfi-dashboard-thread mt-5 space-y-4" data-wolfi-thread>
                <article class="wolfi-dashboard-message wolfi-dashboard-message-assistant rounded-[1.7rem] border border-amber-300/14 bg-amber-300/8 p-4 sm:p-5">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-amber-300/18 bg-black/20 text-sm font-semibold text-amber-100">
                            {{ strtoupper(substr($assistant['name'] ?? 'W', 0, 1)) }}
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-white">{{ $assistant['name'] ?? 'Wolfi' }}</p>
                            <p class="text-xs uppercase tracking-[0.22em] text-amber-200/80">{{ $welcome['title'] ?? 'Wolfi is online' }}</p>
                        </div>
                    </div>

                    <p class="mt-4 text-sm leading-7 text-slate-100">{{ $welcome['message'] ?? '' }}</p>

                    @if ($messageBullets !== [])
                        <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-300">
                            @foreach ($messageBullets as $bullet)
                                <li class="flex gap-3">
                                    <span class="mt-2 inline-flex h-1.5 w-1.5 shrink-0 rounded-full bg-amber-300"></span>
                                    <span>{{ $bullet }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($messageStats !== [])
                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            @foreach ($messageStats as $stat)
                                <div class="rounded-[1.3rem] border border-white/8 bg-black/20 p-3">
                                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $stat['label'] }}</p>
                                    <p class="mt-2 text-sm font-semibold text-white">{{ $stat['value'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>
            </div>

            <div class="mt-5 flex items-center gap-3 rounded-[1.4rem] border border-white/8 bg-white/4 px-4 py-3 text-sm text-slate-300">
                <span class="wolfi-dashboard-status-dot shrink-0" data-wolfi-status-dot></span>
                <span data-wolfi-status>{{ $assistant['status_idle'] ?? 'Ready to guide your next step' }}</span>
            </div>

            <form class="mt-4" data-wolfi-form>
                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                    <label class="block">
                        <span class="sr-only">{{ $assistant['submit_label'] ?? 'Ask Wolfi' }}</span>
                        <input
                            type="text"
                            name="message"
                            autocomplete="off"
                            class="wolfi-dashboard-input w-full rounded-[1.5rem] border border-white/10 bg-black/25 px-4 py-4 text-white outline-none transition placeholder:text-slate-500"
                            placeholder="{{ $assistant['input_placeholder'] ?? '' }}"
                        >
                    </label>

                    <button
                        type="submit"
                        class="wolfi-dashboard-submit inline-flex items-center justify-center rounded-[1.5rem] border border-amber-300/24 bg-amber-300/15 px-6 py-4 text-sm font-semibold text-amber-50 transition hover:border-amber-200/40 hover:bg-amber-300/20"
                    >
                        {{ $assistant['submit_label'] ?? 'Ask Wolfi' }}
                    </button>
                </div>
            </form>

            <p class="mt-3 text-xs leading-6 text-slate-500">
                {{ $assistant['input_help'] ?? '' }}
            </p>
        </div>
    </div>
</section>
