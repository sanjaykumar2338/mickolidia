@php
    $assistant = $wolfiPanel['assistant'] ?? [];
    $welcome = $wolfiPanel['welcome'] ?? [];
    $stats = array_slice($welcome['stats'] ?? [], 0, 3);
    $hubUrl = route('dashboard.wolfi', array_filter([
        'account' => $wolfiPanel['account_id'] ?? null,
    ]));
@endphp

<section class="dashboard-wolfi-entry surface-card overflow-hidden rounded-[2rem] p-5 sm:p-6">
    <div class="grid gap-5 lg:grid-cols-[auto_minmax(0,1fr)] lg:items-center xl:gap-6">
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
            <h3 class="mt-3 max-w-2xl text-2xl font-semibold leading-tight text-white">{{ __('site.dashboard.wolfi.entry_title') }}</h3>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-300">
                {{ $welcome['message'] ?? __('site.dashboard.wolfi.entry_copy') }}
            </p>

            <div class="mt-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                @if ($stats !== [])
                    <div class="grid gap-3 sm:grid-cols-3 xl:flex-1">
                        @foreach ($stats as $stat)
                            <div class="rounded-[1.25rem] border border-white/8 bg-black/18 px-4 py-3">
                                <p class="text-[0.66rem] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $stat['label'] }}</p>
                                <p class="mt-2 text-sm font-semibold text-white">{{ $stat['value'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex w-full min-w-0 flex-col gap-3 xl:ml-6 xl:w-[16rem] xl:flex-none">
                    <a href="{{ $hubUrl }}" class="inline-flex w-full items-center justify-center rounded-full border border-amber-300/28 bg-amber-300/14 px-5 py-3 text-sm font-semibold text-amber-50 transition hover:border-amber-200/50 hover:bg-amber-300/20">
                        {{ __('site.dashboard.wolfi.open_hub') }}
                    </a>
                    <p class="text-center text-xs leading-5 text-slate-500 xl:text-left">{{ __('site.dashboard.wolfi.entry_hint') }}</p>
                </div>
            </div>
        </div>
    </div>
</section>
