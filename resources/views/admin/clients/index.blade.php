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

    <div class="mt-10 surface-panel overflow-hidden rounded-[2rem]">
        <div class="overflow-x-auto">
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
                                <a href="{{ route('admin.clients.show', $client['id']) }}" class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
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
