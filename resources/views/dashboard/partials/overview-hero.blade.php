@php
    $badgeToneClasses = [
        'amber' => 'border-amber-400/20 bg-amber-400/12 text-amber-100',
        'emerald' => 'border-emerald-400/20 bg-emerald-500/12 text-emerald-100',
        'rose' => 'border-rose-400/20 bg-rose-500/12 text-rose-100',
        'sky' => 'border-sky-400/20 bg-sky-500/12 text-sky-100',
        'slate' => 'border-white/10 bg-white/6 text-slate-200',
    ];
    $metricToneClasses = [
        'amber' => 'text-amber-100',
        'emerald' => 'text-emerald-100',
        'rose' => 'text-rose-100',
        'sky' => 'text-sky-100',
        'slate' => 'text-white',
    ];
    $syncToneClass = $badgeToneClasses[$hero['sync_freshness']['tone'] ?? 'slate'] ?? $badgeToneClasses['slate'];
@endphp

<section class="dashboard-hero surface-panel relative overflow-hidden rounded-[2rem] p-5 sm:p-6 lg:p-8">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute left-[-8rem] top-[-8rem] h-56 w-56 rounded-full bg-amber-400/10 blur-3xl"></div>
        <div class="absolute bottom-[-10rem] right-[-6rem] h-64 w-64 rounded-full bg-sky-500/12 blur-3xl"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.04),transparent_32%),linear-gradient(180deg,rgba(255,255,255,0.03),transparent_22%)]"></div>
    </div>

    <div class="relative grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.8fr)] xl:items-start">
        <div class="min-w-0 space-y-5">
            <div class="flex flex-wrap gap-2">
                @foreach ($hero['badges'] as $badge)
                    <span class="{{ $badgeToneClasses[$badge['tone']] ?? $badgeToneClasses['slate'] }} inline-flex rounded-full border px-3 py-1 text-[0.7rem] font-semibold uppercase tracking-[0.22em]">
                        {{ $badge['label'] }}
                    </span>
                @endforeach
            </div>

            <div class="max-w-4xl">
                <p class="text-xs font-semibold uppercase tracking-[0.32em] text-amber-300">Primary account</p>
                <h2 class="mt-3 text-3xl font-semibold text-white sm:text-4xl">{{ $hero['title'] }}</h2>
                <p class="mt-3 break-words text-sm leading-7 text-slate-400">{{ $hero['subtitle'] }}</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($hero['metrics'] as $metric)
                    <article class="rounded-[1.7rem] border border-white/8 bg-black/18 p-4 shadow-[0_18px_45px_rgba(2,6,23,0.22)]">
                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-400">{{ $metric['label'] }}</p>
                        <p class="mt-3 text-2xl font-semibold {{ $metricToneClasses[$metric['tone']] ?? $metricToneClasses['slate'] }}">
                            {{ $metric['value'] }}
                        </p>
                        <p class="mt-2 text-xs text-slate-400">{{ $metric['hint'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <article class="surface-card rounded-[1.85rem] p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Target progress</p>
                <div class="mt-4 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-4xl font-semibold text-white">{{ $primaryAccount['progress_label'] }}</p>
                        <p class="mt-2 text-sm text-slate-400">toward the current phase target</p>
                    </div>
                    <span class="{{ $syncToneClass }} inline-flex rounded-full border px-3 py-1 text-xs font-semibold">
                        {{ $hero['sync_freshness']['label'] }}
                    </span>
                </div>
                <p class="mt-4 text-sm leading-7 text-slate-400">{{ $hero['sync_freshness']['hint'] }}</p>
            </article>

            <article class="surface-card rounded-[1.85rem] p-5 sm:col-span-2 xl:col-span-1">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Account details</p>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/4 px-4 py-3">
                        <dt class="text-slate-400">Platform</dt>
                        <dd class="font-semibold text-white">{{ $hero['platform'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/4 px-4 py-3">
                        <dt class="text-slate-400">Start date</dt>
                        <dd class="font-semibold text-white">{{ $hero['start_date'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/4 px-4 py-3">
                        <dt class="text-slate-400">Phase</dt>
                        <dd class="font-semibold text-white">{{ $hero['challenge_phase'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/4 px-4 py-3">
                        <dt class="text-slate-400">Status</dt>
                        <dd class="font-semibold text-white">{{ $hero['challenge_status'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-white/6 bg-white/4 px-4 py-3">
                        <dt class="text-slate-400">Sync status</dt>
                        <dd class="font-semibold text-white">{{ $hero['sync_status'] }}</dd>
                    </div>
                </dl>
            </article>
        </div>
    </div>
</section>
