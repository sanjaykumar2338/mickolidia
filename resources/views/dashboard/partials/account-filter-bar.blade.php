@php
    $accountCollection = collect($accounts ?? []);
    $statusOptions = $accountCollection
        ->pluck('challenge_status')
        ->filter()
        ->unique()
        ->values();
    $accountCount = $accountCollection->count();
    $activeCount = $accountCollection->filter(fn (array $account): bool => ! ($account['trading_blocked'] ?? false) && ! str($account['challenge_status'] ?? '')->lower()->contains(['failed', 'inactive', 'passed', 'breached']))->count();
    $inactiveCount = max($accountCount - $activeCount, 0);
@endphp

<section class="surface-panel rounded-[2rem] p-4 sm:p-5" data-account-filter>
    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-300">{{ __('Trader workspace') }}</p>
            <h2 class="mt-2 text-2xl font-semibold text-white">{{ __('Welcome back, :name', ['name' => $profile['name'] ?: __('Trader')]) }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-400">{{ __('Filter accounts, open credentials, and jump into live metrics from one mobile-friendly control center.') }}</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(12rem,0.75fr)] xl:min-w-[28rem]">
            <div class="grid grid-cols-3 rounded-full border border-white/8 bg-black/20 p-1">
                <button type="button" class="rounded-full px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-white transition data-[active=true]:bg-amber-400 data-[active=true]:text-slate-950" data-account-filter-tab data-filter="all" data-active="true">
                    {{ __('All') }} <span class="hidden sm:inline">({{ $accountCount }})</span>
                </button>
                <button type="button" class="rounded-full px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400 transition data-[active=true]:bg-amber-400 data-[active=true]:text-slate-950" data-account-filter-tab data-filter="active" data-active="false">
                    {{ __('Active') }} <span class="hidden sm:inline">({{ $activeCount }})</span>
                </button>
                <button type="button" class="rounded-full px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400 transition data-[active=true]:bg-amber-400 data-[active=true]:text-slate-950" data-account-filter-tab data-filter="inactive" data-active="false">
                    {{ __('Inactive') }} <span class="hidden sm:inline">({{ $inactiveCount }})</span>
                </button>
            </div>

            <label class="relative block">
                <span class="sr-only">{{ __('Status filter') }}</span>
                <select data-account-status-filter class="h-full min-h-11 w-full appearance-none rounded-full border border-white/10 bg-white/5 px-4 py-2.5 pr-10 text-sm font-semibold text-white outline-none transition focus:border-amber-300/40">
                    <option value="all">{{ __('All statuses') }}</option>
                    @foreach ($statusOptions as $status)
                        <option value="{{ \Illuminate\Support\Str::slug((string) $status) }}">{{ $status }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0l-4.25-4.51a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                </svg>
            </label>
        </div>
    </div>

    @if ($accountCollection->isNotEmpty())
        <div class="mt-5 grid gap-3 lg:grid-cols-2 2xl:grid-cols-3">
            @foreach ($accountCollection as $account)
                @php
                    $isInactive = ($account['trading_blocked'] ?? false) || str($account['challenge_status'] ?? '')->lower()->contains(['failed', 'inactive', 'passed', 'breached']);
                    $statusSlug = \Illuminate\Support\Str::slug((string) ($account['challenge_status'] ?? 'status'));
                    $toneClass = match ($account['status_tone'] ?? 'slate') {
                        'emerald' => 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100',
                        'rose' => 'border-rose-400/20 bg-rose-500/10 text-rose-100',
                        'amber' => 'border-amber-400/20 bg-amber-400/10 text-amber-100',
                        'sky' => 'border-sky-400/20 bg-sky-500/10 text-sky-100',
                        default => 'border-white/10 bg-white/5 text-slate-200',
                    };
                @endphp
                <article
                    class="rounded-[1.45rem] border border-white/8 bg-black/18 p-4 transition"
                    data-account-filter-card
                    data-account-state="{{ $isInactive ? 'inactive' : 'active' }}"
                    data-account-status="{{ $statusSlug }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-white">{{ $account['reference'] }}</p>
                            <p class="mt-1 truncate text-xs uppercase tracking-[0.18em] text-slate-400">{{ $account['plan'] }}</p>
                        </div>
                        <span class="{{ $toneClass }} shrink-0 rounded-full border px-3 py-1 text-xs font-semibold">
                            {{ $account['challenge_status'] }}
                        </span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-2xl border border-white/6 bg-white/4 px-3 py-2.5">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Balance') }}</p>
                            <p class="mt-1 font-semibold text-white">{{ $account['balance'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/6 bg-white/4 px-3 py-2.5">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Phase') }}</p>
                            <p class="mt-1 font-semibold text-white">{{ $account['challenge_phase'] }}</p>
                        </div>
                    </div>

                    @if (! empty($account['dashboard_url']))
                        <a href="{{ $account['dashboard_url'] }}" class="mt-4 inline-flex w-full items-center justify-center rounded-full border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-amber-300/30 hover:bg-amber-300/10">
                            {{ __('Open metrics') }}
                        </a>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="mt-5 hidden rounded-[1.35rem] border border-dashed border-white/12 bg-white/3 px-4 py-5 text-center text-sm leading-7 text-slate-400" data-account-filter-empty>
            {{ __('No accounts match the selected filters.') }}
        </div>
    @endif
</section>
