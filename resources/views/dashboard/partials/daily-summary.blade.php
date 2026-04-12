<section class="surface-panel rounded-[2rem] p-5 sm:p-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{{ __('Daily summary') }}</p>
            <h3 class="mt-3 text-2xl font-semibold text-white">{{ __('Recent trading activity') }}</h3>
        </div>
        <p class="max-w-2xl text-sm leading-7 text-slate-400">
            {{ $dailySummary['message'] }}
        </p>
    </div>

    @if ($dailySummary['is_available'] && ! empty($dailySummary['rows']))
        <div class="mt-6 grid gap-3 lg:grid-cols-2 2xl:grid-cols-3">
            @foreach ($dailySummary['rows'] as $row)
                <article class="rounded-[1.55rem] border border-white/8 bg-black/18 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-white">{{ $row['date'] }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.2em] text-slate-400">{{ $row['source'] }}</p>
                        </div>
                        <span class="rounded-full border border-amber-400/18 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-100">
                            {{ $row['activity'] }}
                        </span>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-2xl border border-white/6 bg-white/4 px-3 py-2.5">
                            <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Volume') }}</dt>
                            <dd class="mt-1 font-semibold text-white">{{ $row['volume'] }}</dd>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-white/4 px-3 py-2.5">
                            <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Last activity') }}</dt>
                            <dd class="mt-1 font-semibold text-white">{{ $row['last_activity_at'] }}</dd>
                        </div>
                    </dl>
                </article>
            @endforeach
        </div>
    @else
        <div class="mt-6 rounded-[1.55rem] border border-dashed border-white/12 bg-white/3 px-5 py-6 text-sm leading-7 text-slate-400">
            {{ __('Daily summary will appear once a synced day includes trade activity.') }}
        </div>
    @endif
</section>
