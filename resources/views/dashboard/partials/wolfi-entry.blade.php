@php
    $assistant = $wolfiPanel['assistant'] ?? [];
    $welcome = $wolfiPanel['welcome'] ?? [];
    $stats = array_slice($welcome['stats'] ?? [], 0, 3);
    $hubUrl = route('dashboard.wolfi', array_filter([
        'account' => $wolfiPanel['account_id'] ?? null,
    ]));
@endphp

<section class="dashboard-wolfi-entry surface-card overflow-hidden rounded-[2rem] p-5 sm:p-6">
    <div class="grid gap-5 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:items-center">
        <div class="dashboard-wolfi-entry-orbit">
            <div class="wolfi-dashboard-avatar-shell dashboard-wolfi-entry-avatar" aria-hidden="true">
                <span class="wolfi-dashboard-avatar-ring wolfi-dashboard-avatar-ring-outer"></span>
                <span class="wolfi-dashboard-avatar-ring wolfi-dashboard-avatar-ring-inner"></span>
                <img
                    src="{{ asset($assistant['avatar_asset'] ?? 'newfolder/IMG_8542.png') }}"
                    alt=""
                    class="wolfi-dashboard-avatar-image"
                    loading="lazy"
                    decoding="async"
                >
            </div>
        </div>

        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-300">{{ __('site.dashboard.wolfi.entry_eyebrow') }}</p>
            <h3 class="mt-3 text-2xl font-semibold text-white">{{ __('site.dashboard.wolfi.entry_title') }}</h3>
            <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">
                {{ $welcome['message'] ?? __('site.dashboard.wolfi.entry_copy') }}
            </p>

            @if ($stats !== [])
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    @foreach ($stats as $stat)
                        <div class="rounded-[1.25rem] border border-white/8 bg-black/18 px-4 py-3">
                            <p class="text-[0.66rem] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $stat['label'] }}</p>
                            <p class="mt-2 text-sm font-semibold text-white">{{ $stat['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-3 lg:min-w-[12rem]">
            <a href="{{ $hubUrl }}" class="inline-flex items-center justify-center rounded-full border border-amber-300/28 bg-amber-300/14 px-5 py-3 text-sm font-semibold text-amber-50 transition hover:border-amber-200/50 hover:bg-amber-300/20">
                {{ __('site.dashboard.wolfi.open_hub') }}
            </a>
            <p class="text-center text-xs leading-5 text-slate-500 lg:text-left">{{ __('site.dashboard.wolfi.entry_hint') }}</p>
        </div>
    </div>
</section>
