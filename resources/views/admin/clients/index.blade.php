@extends('admin.layout')

@section('title', __('site.admin.clients.title').' | '.__('site.meta.brand'))

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <span class="section-label">{{ __('site.admin.eyebrow') }}</span>
            <h1 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ __('site.admin.clients.title') }}</h1>
            <p class="mt-4 max-w-3xl text-base leading-8 text-slate-300">{{ __('site.admin.clients.description') }}</p>
        </div>
        <div class="gold-pill rounded-full px-4 py-2 text-sm font-medium">
            {{ __('site.admin.clients.status_hint', ['count' => $clients->count()]) }}
        </div>
    </div>

    @if (! empty($metaApiSummary ?? []))
        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                'MetaApi total' => $metaApiSummary['total'] ?? 0,
                'Connected' => $metaApiSummary['connected'] ?? 0,
                'Disconnected' => $metaApiSummary['disconnected'] ?? 0,
                'Stale' => $metaApiSummary['stale'] ?? 0,
                'Breached' => $metaApiSummary['breached'] ?? 0,
                'Sync issues' => $metaApiSummary['sync_issues'] ?? 0,
                'Onboarding queue' => $metaApiSummary['onboarding_queue'] ?? 0,
                'Ready to trade' => $metaApiSummary['ready_to_trade'] ?? 0,
                'Pool available' => $metaApiSummary['pool_available'] ?? 0,
                'Pool MetaApi unassigned' => $metaApiSummary['pool_unassigned_metaapi'] ?? 0,
            ] as $label => $value)
                <div class="surface-panel rounded-[1.4rem] p-5">
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $label }}</dt>
                    <dd class="mt-3 text-2xl font-semibold text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </div>

        @if (! empty($metaApiSummary['validated_account_rows'] ?? []))
            <section class="mt-6 surface-panel rounded-[1.6rem] p-5">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-300">Validated MetaApi accounts</p>
                        <h2 class="mt-2 text-xl font-semibold text-white">{{ implode(', ', $metaApiSummary['validated_accounts'] ?? []) }}</h2>
                    </div>
                    <p class="text-sm text-slate-400">Phase 1 visibility scope only</p>
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-400">
                    Click View Metrics to review balance, equity, drawdown, positions, history, and sync details.
                </p>
                <div class="mt-5 space-y-3 md:hidden">
                    @foreach ($metaApiSummary['validated_account_rows'] as $row)
                        <article class="rounded-[1.3rem] border border-white/6 bg-black/15 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-base font-semibold text-white">{{ $row['login'] }}</p>
                                    <p class="mt-1 break-words text-xs text-slate-400">{{ $row['reference'] }}</p>
                                </div>
                                @if (! empty($row['metrics_url']))
                                    <a href="{{ $row['metrics_url'] }}" class="shrink-0 rounded-full border border-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                                        View
                                    </a>
                                @endif
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                @foreach ([
                                    'Connection' => $row['connection'],
                                    'Provisioning' => $row['provisioning'],
                                    'Lifecycle' => $row['lifecycle'],
                                    'Ready' => $row['ready_to_trade'],
                                    'Pool' => $row['pool_source'],
                                    'Last sync' => $row['last_sync'],
                                ] as $label => $value)
                                    <div>
                                        <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $label }}</dt>
                                        <dd class="mt-1 break-words text-slate-200">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </article>
                    @endforeach
                </div>

                <div class="mt-5 hidden overflow-x-auto md:block">
                    <table class="min-w-full divide-y divide-white/6 text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase tracking-[0.18em] text-slate-400">
                            <tr>
                                @foreach (['Login', 'Reference', 'Source', 'Connection', 'Provisioning', 'Pool Source', 'MetaApi Connected', 'Lifecycle', 'Onboarding', 'Ready', 'Last sync', 'Actions'] as $heading)
                                    <th class="px-3 py-2 font-semibold">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/6">
                            @foreach ($metaApiSummary['validated_account_rows'] as $row)
                                <tr>
                                    <td class="px-3 py-3 font-semibold text-white">{{ $row['login'] }}</td>
                                    <td class="px-3 py-3">{{ $row['reference'] }}</td>
                                    <td class="px-3 py-3">{{ $row['source'] }}</td>
                                    <td class="px-3 py-3">{{ $row['connection'] }}</td>
                                    <td class="px-3 py-3">{{ $row['provisioning'] }}</td>
                                    <td class="px-3 py-3">{{ $row['pool_source'] }}</td>
                                    <td class="px-3 py-3">{{ $row['metaapi_connected'] }}</td>
                                    <td class="px-3 py-3">{{ $row['lifecycle'] }}</td>
                                    <td class="px-3 py-3">{{ $row['onboarding'] }}</td>
                                    <td class="px-3 py-3">{{ $row['ready_to_trade'] }}</td>
                                    <td class="px-3 py-3">{{ $row['last_sync'] }}</td>
                                    <td class="px-3 py-3">
                                        @if (! empty($row['metrics_url']))
                                            <a href="{{ $row['metrics_url'] }}" class="inline-flex rounded-full border border-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                                                View Metrics
                                            </a>
                                        @else
                                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Unavailable</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif

    <div class="mt-10 surface-panel rounded-[2rem] p-4 md:p-0 md:overflow-hidden">
        <div class="space-y-4 md:hidden">
            @forelse ($clients as $client)
                @php
                    $statusClass = match (strtolower($client['account_status_key'])) {
                        'active', 'completed' => 'border-emerald-400/25 bg-emerald-500/12 text-emerald-100',
                        'passed' => 'border-sky-400/25 bg-sky-500/12 text-sky-100',
                        'failed', 'cancelled' => 'border-rose-400/25 bg-rose-500/12 text-rose-100',
                        default => 'border-amber-400/25 bg-amber-400/12 text-amber-50',
                    };
                @endphp
                <article class="rounded-[1.4rem] border border-white/6 bg-black/15 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-white">{{ $client['full_name'] }}</p>
                            <p class="mt-1 break-words text-sm text-slate-400">{{ $client['email'] }}</p>
                        </div>
                        <span class="{{ $statusClass }} shrink-0 rounded-full border px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.14em]">
                            {{ $client['account_status'] }}
                        </span>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        @foreach ([
                            __('site.admin.table.country') => $client['country'],
                            __('site.admin.table.plan_selected') => $client['plan_selected'],
                            __('site.admin.table.payment_amount') => $client['payment_amount'],
                            __('site.admin.table.payment_status') => $client['payment_status'],
                            __('site.admin.table.order_date') => $client['order_date'],
                            __('site.admin.account.reference') => $client['account_reference'] ?: '—',
                        ] as $label => $value)
                            <div>
                                <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $label }}</dt>
                                <dd class="mt-1 break-words font-medium text-slate-200">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('admin.clients.metrics', $client['id']) }}" class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                            {{ __('site.admin.table.view_metrics') }}
                        </a>
                        @if ($client['can_activate'])
                            <form method="POST" action="{{ route('admin.clients.activate', $client['id']) }}">
                                @csrf
                                <button type="submit" class="rounded-full border border-amber-400/25 bg-amber-400/10 px-4 py-2 text-sm font-semibold text-amber-100 transition hover:border-amber-300/40 hover:bg-amber-400/18">
                                    {{ __('site.admin.table.activate_account') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-[1.4rem] border border-dashed border-white/10 px-4 py-8 text-center text-slate-400">
                    {{ __('site.admin.clients.empty') }}
                </div>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-white/6 text-left text-sm text-slate-300">
                <thead class="bg-white/3 text-xs uppercase tracking-[0.2em] text-slate-400">
                    <tr>
                        <th class="px-6 py-4 font-semibold">{{ __('site.admin.table.full_name') }}</th>
                        <th class="px-6 py-4 font-semibold">{{ __('site.admin.table.email') }}</th>
                        <th class="px-6 py-4 font-semibold">{{ __('site.admin.table.country') }}</th>
                        <th class="px-6 py-4 font-semibold">{{ __('site.admin.table.plan_selected') }}</th>
                        <th class="px-6 py-4 font-semibold">{{ __('site.admin.table.payment_amount') }}</th>
                        <th class="px-6 py-4 font-semibold">{{ __('site.admin.table.payment_provider') }}</th>
                        <th class="px-6 py-4 font-semibold">{{ __('site.admin.table.payment_status') }}</th>
                        <th class="px-6 py-4 font-semibold">{{ __('site.admin.table.order_date') }}</th>
                        <th class="px-6 py-4 font-semibold">{{ __('site.admin.table.account_status') }}</th>
                        <th class="px-6 py-4 font-semibold">{{ __('site.admin.table.metrics') }}</th>
                        <th class="px-6 py-4 font-semibold">{{ __('site.admin.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/6">
                    @forelse ($clients as $client)
                        @php
                            $statusClass = match (strtolower($client['account_status_key'])) {
                                'active', 'completed' => 'border-emerald-400/25 bg-emerald-500/12 text-emerald-100',
                                'passed' => 'border-sky-400/25 bg-sky-500/12 text-sky-100',
                                'failed', 'cancelled' => 'border-rose-400/25 bg-rose-500/12 text-rose-100',
                                default => 'border-amber-400/25 bg-amber-400/12 text-amber-50',
                            };
                        @endphp
                        <tr class="align-middle">
                            <td class="px-6 py-5">
                                <p class="font-semibold text-white">{{ $client['full_name'] }}</p>
                            </td>
                            <td class="px-6 py-5">{{ $client['email'] }}</td>
                            <td class="px-6 py-5">{{ $client['country'] }}</td>
                            <td class="px-6 py-5">{{ $client['plan_selected'] }}</td>
                            <td class="px-6 py-5 font-semibold text-white">{{ $client['payment_amount'] }}</td>
                            <td class="px-6 py-5">{{ $client['payment_provider'] }}</td>
                            <td class="px-6 py-5">{{ $client['payment_status'] }}</td>
                            <td class="px-6 py-5">{{ $client['order_date'] }}</td>
                            <td class="px-6 py-5">
                                <div class="flex flex-col gap-2">
                                    <span class="{{ $statusClass }} inline-flex w-fit rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]">
                                        {{ $client['account_status'] }}
                                    </span>
                                    @if ($client['account_reference'])
                                        <span class="text-xs font-medium tracking-[0.08em] text-slate-400">
                                            {{ __('site.admin.account.reference') }}: {{ $client['account_reference'] }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <a href="{{ route('admin.clients.metrics', $client['id']) }}" class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                                    {{ __('site.admin.table.view_metrics') }}
                                </a>
                            </td>
                            <td class="px-6 py-5">
                                @if ($client['can_activate'])
                                    <form method="POST" action="{{ route('admin.clients.activate', $client['id']) }}">
                                        @csrf
                                        <button type="submit" class="rounded-full border border-amber-400/25 bg-amber-400/10 px-4 py-2 text-sm font-semibold text-amber-100 transition hover:border-amber-300/40 hover:bg-amber-400/18">
                                            {{ __('site.admin.table.activate_account') }}
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                        {{ __('site.admin.table.no_action') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-6 py-10 text-center text-slate-400">
                                {{ __('site.admin.clients.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
