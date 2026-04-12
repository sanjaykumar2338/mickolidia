@php
    $toneClasses = [
        'amber' => 'text-amber-100',
        'emerald' => 'text-emerald-100',
        'rose' => 'text-rose-100',
        'sky' => 'text-sky-100',
        'slate' => 'text-white',
    ];
@endphp

<section class="surface-panel rounded-[2rem] p-5 sm:p-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{{ __('Statistics') }}</p>
            <h3 class="mt-3 text-2xl font-semibold text-white">{{ __('Compact performance breakdown') }}</h3>
        </div>
        <p class="max-w-2xl text-sm leading-7 text-slate-400">
            {{ $statisticsGrid['message'] }}
        </p>
    </div>

    @if ($statisticsGrid['is_available'] && ! empty($statisticsGrid['cards']))
        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
            @foreach ($statisticsGrid['cards'] as $card)
                <article class="rounded-[1.45rem] border border-white/8 bg-black/18 p-4">
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ $card['label'] }}</p>
                    <p class="mt-3 text-2xl font-semibold {{ $toneClasses[$card['tone']] ?? $toneClasses['slate'] }}">{{ $card['value'] }}</p>
                    @if (! empty($card['hint']))
                        <p class="mt-2 text-xs leading-5 text-slate-400">{{ $card['hint'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @else
        <div class="mt-6 rounded-[1.55rem] border border-dashed border-white/12 bg-white/3 px-5 py-6 text-sm leading-7 text-slate-400">
            {{ __('Statistics will appear once reliable snapshots or detailed closed-trade rows are available.') }}
        </div>
    @endif

    @if (! empty($statisticsGrid['notes']))
        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            @foreach ($statisticsGrid['notes'] as $note)
                <p class="rounded-[1.25rem] border border-white/8 bg-white/4 px-4 py-3 text-xs leading-6 text-slate-400">
                    {{ $note }}
                </p>
            @endforeach
        </div>
    @endif
</section>
