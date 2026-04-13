@php
    $toneClasses = [
        'amber' => 'border-amber-400/18 bg-amber-400/10 text-amber-100',
        'emerald' => 'border-emerald-400/18 bg-emerald-500/10 text-emerald-100',
        'rose' => 'border-rose-400/18 bg-rose-500/10 text-rose-100',
        'sky' => 'border-sky-400/18 bg-sky-500/10 text-sky-100',
        'slate' => 'border-white/10 bg-white/5 text-slate-200',
    ];
    $barClasses = [
        'amber' => 'bg-amber-400',
        'emerald' => 'bg-emerald-400',
        'rose' => 'bg-rose-400',
        'sky' => 'bg-sky-400',
        'slate' => 'bg-slate-300',
    ];
@endphp

<section id="rule-monitoring" class="surface-panel rounded-[2rem] p-5 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{{ __('Rule monitoring') }}</p>
            <h3 class="mt-3 text-2xl font-semibold text-white">{{ __('Targets, drawdown, and trading-day control') }}</h3>
            <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-400">
                {{ __('Core Wolforix challenge rules stay visible so traders can see pass progress and breach risk at a glance.') }}
            </p>
        </div>

        @if (! empty($primaryAccount['challenge_phase']))
            <span class="inline-flex w-fit rounded-full border border-white/10 bg-white/6 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-200">
                {{ $primaryAccount['challenge_phase'] }}
            </span>
        @endif
    </div>

    @if (! empty($progressTracks))
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            @foreach ($progressTracks as $track)
                <article class="flex min-h-[12rem] flex-col rounded-[1.55rem] border {{ $track['tone'] === 'rose' ? 'border-rose-400/28 bg-rose-500/10' : 'border-white/8 bg-black/18' }} p-4 sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <p class="max-w-full text-sm font-semibold leading-6 text-white sm:max-w-[70%]">{{ $track['label'] }}</p>
                        <span class="{{ $toneClasses[$track['tone']] ?? $toneClasses['slate'] }} inline-flex w-fit max-w-full shrink-0 rounded-full border px-3 py-1 text-xs font-semibold leading-5">
                            {{ $track['value_label'] }}
                        </span>
                    </div>

                    <dl class="mt-4 grid gap-2 sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/6 bg-white/4 px-3 py-3">
                            <dt class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Current') }}</dt>
                            <dd class="mt-1 break-words text-sm font-semibold text-white">{{ $track['current'] }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-white/4 px-3 py-3">
                            <dt class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $track['target_label'] ?? __('Limit') }}</dt>
                            <dd class="mt-1 break-words text-sm font-semibold text-white">{{ $track['target'] }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-white/8">
                        <div class="h-full rounded-full {{ $barClasses[$track['tone']] ?? $barClasses['amber'] }}" style="width: {{ $track['value'] }}%"></div>
                    </div>

                    <p class="mt-3 text-sm leading-6 {{ $track['tone'] === 'rose' ? 'text-rose-100/80' : 'text-slate-400' }}">
                        {{ $track['meta'] }}
                    </p>
                </article>
            @endforeach
        </div>
    @else
        <div class="mt-6 rounded-[1.55rem] border border-dashed border-white/12 bg-white/3 px-5 py-6 text-sm leading-7 text-slate-400">
            {{ __('Rule monitoring appears once a challenge account is linked and synced.') }}
        </div>
    @endif
</section>
