@php
    $compact = $compact ?? false;
@endphp

<section class="surface-panel rounded-[2rem] p-6 sm:p-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">{{ __('site.trial.connector.download_title') }}</p>
            <h2 class="mt-3 text-2xl font-semibold text-white">{{ __('site.trial.connector.title') }}</h2>
            <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-300">{{ __('site.trial.connector.description') }}</p>
        </div>
        <div class="inline-flex rounded-full border px-4 py-2 text-sm font-semibold {{ $connector['status_badge'] }}">
            {{ $connector['status_label'] }}
        </div>
    </div>

    <div class="mt-6 grid gap-3 sm:grid-cols-2">
        <div>
            <a href="{{ $connector['download_url'] }}" class="primary-cta justify-center rounded-full px-6 py-3 text-sm font-semibold" download="{{ $connector['download_file_name'] ?? true }}">
                {{ __('site.trial.connector.download_button') }}
            </a>
            <p class="mt-3 text-xs leading-5 text-slate-400">{{ __('site.trial.connector.download_copy') }}</p>
        </div>
        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3 text-sm leading-6 text-slate-300">
            {{ $connector['last_connected_at'] ? __('site.trial.connector.last_connected', ['time' => $connector['last_connected_at']]) : __('site.trial.connector.waiting_sync') }}
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-white/6 bg-black/15 p-4">
        <h3 class="text-sm font-semibold text-white">{{ __('site.trial.connector.details_title') }}</h3>
        <div class="mt-4 space-y-3">
            @foreach ([
                'base_url' => __('site.trial.connector.base_url'),
                'account_reference' => __('site.trial.connector.account_reference'),
                'secret_token' => __('site.trial.connector.secret_token'),
            ] as $field => $label)
                @php
                    $displayValue = $field === 'secret_token' ? $connector['masked_secret_token'] : $connector[$field];
                    $copyValue = $connector[$field];
                @endphp
                <div class="grid gap-2 rounded-2xl border border-white/6 bg-white/4 px-4 py-3 sm:grid-cols-[10rem_minmax(0,1fr)_auto] sm:items-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $label }}</p>
                    <p
                        class="break-all font-mono text-sm text-white"
                        @if ($field === 'secret_token')
                            data-secret-token-display
                            data-masked-value="{{ $connector['masked_secret_token'] }}"
                            data-revealed-value="{{ $connector['secret_token'] }}"
                        @endif
                    >{{ $displayValue }}</p>
                    <div class="flex flex-wrap gap-2 sm:justify-end">
                        @if ($field === 'secret_token')
                            <button
                                type="button"
                                class="rounded-full border border-white/10 px-4 py-2 text-xs font-semibold text-slate-200 transition hover:border-amber-300/40 hover:text-amber-100"
                                data-secret-token-toggle
                                data-reveal-label="{{ __('site.trial.connector.reveal') }}"
                                data-hide-label="{{ __('site.trial.connector.hide') }}"
                            >
                                {{ __('site.trial.connector.reveal') }}
                            </button>
                        @endif
                        <button
                            type="button"
                            class="rounded-full border border-white/10 px-4 py-2 text-xs font-semibold text-slate-200 transition hover:border-amber-300/40 hover:text-amber-100"
                            data-copy-value="{{ $copyValue }}"
                            data-copy-label="{{ __('site.trial.connector.copy') }}"
                            data-copied-label="{{ __('site.trial.connector.copied') }}"
                        >
                            {{ __('site.trial.connector.copy') }}
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @unless ($compact)
        <div class="mt-6 grid gap-4 lg:grid-cols-[1fr_0.85fr]">
            <ol class="space-y-3">
                @foreach (trans('site.trial.connector.steps') as $index => $step)
                    <li class="flex gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3 text-sm leading-6 text-slate-300">
                        <span class="inline-flex h-7 w-7 flex-none items-center justify-center rounded-full border border-amber-300/25 bg-amber-300/12 text-xs font-semibold text-amber-100">{{ $index + 1 }}</span>
                        <span>{{ $step }}</span>
                    </li>
                @endforeach
            </ol>

            <div class="space-y-3">
                @foreach (trans('site.trial.connector.notes') as $note)
                    <p class="rounded-2xl border border-sky-400/12 bg-sky-500/8 px-4 py-3 text-sm leading-6 text-sky-50">{{ $note }}</p>
                @endforeach
            </div>
        </div>
    @endunless
</section>
