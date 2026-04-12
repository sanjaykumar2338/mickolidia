@php
    $shareUrl = route('dashboard');
    $shareText = __('Wolforix account metrics dashboard');
@endphp

<div class="fixed inset-0 z-[9998] hidden items-end justify-center bg-slate-950/78 p-3 backdrop-blur-md sm:items-center sm:p-6" data-dashboard-modal="credentials" role="dialog" aria-modal="true" aria-labelledby="dashboard-credentials-title">
    <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-[2rem] border border-white/10 bg-slate-950 p-5 shadow-2xl shadow-black/50 sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{{ __('Secure access') }}</p>
                <h3 id="dashboard-credentials-title" class="mt-3 text-2xl font-semibold text-white">{{ $mt5Access['title'] }}</h3>
                <p class="mt-3 text-sm leading-7 text-slate-400">{{ $mt5Access['message'] }}</p>
            </div>
            <button type="button" data-dashboard-modal-close class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 transition hover:border-white/20 hover:bg-white/10 hover:text-white">
                <span class="sr-only">{{ __('Close') }}</span>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            @forelse ($mt5Access['fields'] as $field)
                <article class="rounded-[1.35rem] border border-white/8 bg-white/[0.035] p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ $field['label'] }}</p>
                            <p class="mt-2 break-words text-base font-semibold {{ ! empty($field['is_secret']) ? 'text-amber-100' : 'text-white' }}">{{ $field['value'] }}</p>
                        </div>
                        @if (empty($field['is_secret']) && filled($field['value']))
                            <button type="button" data-dashboard-copy="{{ $field['value'] }}" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 transition hover:border-amber-300/30 hover:bg-amber-300/10 hover:text-white">
                                <span class="sr-only">{{ __('Copy') }}</span>
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M9 9h9v11H9z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                                    <path d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                </svg>
                            </button>
                        @endif
                    </div>
                    @if (! empty($field['hint']))
                        <p class="mt-3 text-xs leading-5 text-slate-400">{{ $field['hint'] }}</p>
                    @endif
                </article>
            @empty
                <div class="rounded-[1.35rem] border border-dashed border-white/12 bg-white/3 px-4 py-5 text-sm leading-7 text-slate-400 sm:col-span-2">
                    {{ __('Credentials are not available for this account yet.') }}
                </div>
            @endforelse
        </div>

        <p class="mt-5 rounded-[1.35rem] border border-amber-400/16 bg-amber-400/10 px-4 py-3 text-xs leading-6 text-amber-50/75">{{ $mt5Access['privacy_note'] }}</p>
    </div>
</div>

<div class="fixed inset-0 z-[9998] hidden items-end justify-center bg-slate-950/78 p-3 backdrop-blur-md sm:items-center sm:p-6" data-dashboard-modal="share" role="dialog" aria-modal="true" aria-labelledby="dashboard-share-title">
    <div class="w-full max-w-xl rounded-[2rem] border border-white/10 bg-slate-950 p-5 shadow-2xl shadow-black/50 sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{{ __('Share metrics') }}</p>
                <h3 id="dashboard-share-title" class="mt-3 text-2xl font-semibold text-white">{{ __('Share your Wolforix dashboard link') }}</h3>
                <p class="mt-3 text-sm leading-7 text-slate-400">{{ __('Copy the dashboard link for support, review, or your own records. Private account access still requires authentication.') }}</p>
            </div>
            <button type="button" data-dashboard-modal-close class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 transition hover:border-white/20 hover:bg-white/10 hover:text-white">
                <span class="sr-only">{{ __('Close') }}</span>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div class="mt-6 rounded-[1.35rem] border border-white/8 bg-white/[0.035] p-4">
            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('Metrics link') }}</p>
            <p class="mt-2 break-all text-sm font-semibold text-white">{{ $shareUrl }}</p>
            <button type="button" data-dashboard-copy="{{ $shareUrl }}" class="mt-4 inline-flex w-full items-center justify-center rounded-full border border-amber-300/30 bg-amber-300/15 px-4 py-3 text-sm font-semibold text-amber-50 transition hover:border-amber-200/50 hover:bg-amber-300/22">
                {{ __('Copy link') }}
            </button>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($shareUrl) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-full border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/10">
                {{ __('LinkedIn') }}
            </a>
            <a href="https://twitter.com/intent/tweet?url={{ urlencode($shareUrl) }}&text={{ urlencode($shareText) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-full border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/10">
                {{ __('X') }}
            </a>
            <a href="mailto:?subject={{ rawurlencode($shareText) }}&body={{ rawurlencode($shareUrl) }}" class="inline-flex items-center justify-center rounded-full border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/10">
                {{ __('Email') }}
            </a>
        </div>
    </div>
</div>
