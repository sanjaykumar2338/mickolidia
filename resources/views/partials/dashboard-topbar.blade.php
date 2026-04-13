@php
    $syncToneClasses = [
        'amber' => 'border-amber-400/20 bg-amber-400/12 text-amber-100',
        'emerald' => 'border-emerald-400/20 bg-emerald-500/12 text-emerald-100',
        'rose' => 'border-rose-400/20 bg-rose-500/12 text-rose-100',
        'sky' => 'border-sky-400/20 bg-sky-500/12 text-sky-100',
        'slate' => 'border-white/10 bg-white/6 text-slate-200',
    ];
@endphp

<header class="sticky top-0 z-30 border-b border-white/6 bg-slate-950/82 px-4 py-5 backdrop-blur-xl sm:px-6 lg:px-8">
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
        <div class="min-w-0 lg:max-w-[38rem] 2xl:max-w-[42rem]">
            <span class="section-label">{{ __('site.public_layout.preview_badge') }}</span>
            <h1 class="mt-4 text-3xl font-semibold text-white">@yield('dashboard-title', __('site.dashboard.preview_title'))</h1>
            <p class="mt-2 max-w-2xl text-sm leading-7 text-slate-400">@yield('dashboard-subtitle', __('site.dashboard.preview_subtitle'))</p>
        </div>

        <div class="flex flex-wrap items-center gap-3 lg:max-w-[56rem] lg:justify-end">
            <x-language-switcher compact class="order-first hidden shrink-0 lg:block" />

            @if (! empty($primaryAccount))
                <div class="{{ $syncToneClasses[$primaryAccount['status_tone'] ?? 'slate'] ?? $syncToneClasses['slate'] }} rounded-full border px-4 py-2 text-sm">
                    {{ $primaryAccount['challenge_status'] }}
                </div>
                <div class="rounded-full border border-white/10 bg-white/6 px-4 py-2 text-sm text-slate-200">
                    {{ $primaryAccount['platform'] }} • {{ $primaryAccount['challenge_phase'] }}
                </div>
                <div class="{{ $syncToneClasses[$primaryAccount['sync_freshness_tone'] ?? 'slate'] ?? $syncToneClasses['slate'] }} rounded-full border px-4 py-2 text-sm">
                    {{ $primaryAccount['sync_freshness'] }}
                </div>
            @else
                <div class="rounded-full border border-sky-400/20 bg-sky-500/10 px-4 py-2 text-sm text-sky-100">
                    {{ __('site.dashboard.status_badge') }}
                </div>
            @endif
        </div>
    </div>
</header>
