@php
    $hubUrl = route('dashboard.wolfi', array_filter([
        'account' => $wolfiPanel['account_id'] ?? null,
    ]));
    $dashboardWolfiImage = asset((string) config('wolfi.images.dashboard'));
@endphp

<section class="dashboard-wolfi-entry surface-card overflow-hidden rounded-[2rem] p-5 sm:p-6">
    <div class="grid gap-6 lg:grid-cols-[minmax(14rem,0.86fr)_minmax(0,1fr)] lg:items-center">
        <div class="dashboard-wolfi-entry-orbit">
            <div class="dashboard-wolfi-entry-avatar">
                <span class="dashboard-wolfi-ring-avatar">
                    <img
                        src="{{ $dashboardWolfiImage }}"
                        alt="{{ __('site.dashboard.nav.wolfi_hub') }}"
                        class="h-full w-full object-cover object-[50%_30%]"
                        loading="lazy"
                        decoding="async"
                    >
                </span>
            </div>

            <span class="inline-flex rounded-full border border-amber-300/18 bg-amber-300/10 px-3 py-1.5 text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-amber-100">
                {{ __('site.dashboard.wolfi.assistant.response_label') }}
            </span>
        </div>

        <div class="min-w-0">
            <div class="max-w-4xl">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-300">{{ __('site.dashboard.wolfi.entry_eyebrow') }}</p>
                <h3 class="mt-3 text-2xl font-semibold leading-tight text-white sm:text-[2.2rem]">{{ __('site.dashboard.wolfi.entry_title') }}</h3>
                <p class="mt-3 break-words text-sm leading-7 text-slate-300">
                    {{ __('site.dashboard.wolfi.entry_copy') }}
                </p>
            </div>

            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="max-w-3xl text-xs leading-5 text-slate-500">
                    {{ __('site.dashboard.wolfi.entry_hint') }}
                </p>
                <a href="{{ $hubUrl }}" class="inline-flex w-full items-center justify-center rounded-full border border-amber-300/28 bg-amber-300/14 px-5 py-3 text-sm font-semibold text-amber-50 transition hover:border-amber-200/50 hover:bg-amber-300/20 sm:w-auto sm:min-w-[12rem]">
                    {{ __('site.dashboard.wolfi.open_hub') }}
                </a>
            </div>
        </div>
    </div>
</section>
