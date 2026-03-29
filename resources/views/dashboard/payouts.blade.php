@extends('layouts.dashboard')

@section('title', __('site.dashboard.payouts_page.title').' | '.__('site.meta.brand'))
@section('dashboard-title', __('site.dashboard.payouts_page.title'))
@section('dashboard-subtitle', __('site.dashboard.payouts_page.subtitle'))

@section('content')
    <div class="space-y-6">
        <div class="grid gap-5 xl:grid-cols-3 md:grid-cols-2">
            <x-stat-card :label="__('site.dashboard.payouts.next_window')" :value="$payoutSummary['next_window']" :hint="$payoutSummary['status']" />
            <x-stat-card :label="__('site.dashboard.labels.eligible_profit')" :value="$payoutSummary['eligible_profit']" :hint="$payoutSummary['cycle_note']" />
            <x-stat-card :label="__('site.dashboard.nav.payouts')" :value="$payoutSummary['status']" :hint="__('site.dashboard.payouts.queue_copy')" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
            <section class="surface-panel rounded-[2rem] p-6">
                <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.dashboard.payouts.queue_title') }}</p>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-400">Payout timing now reads from the linked trading account lifecycle, funded state, and stored payout eligibility fields.</p>

                <div class="mt-6 space-y-3">
                    <div class="rounded-2xl border border-white/8 bg-white/3 px-4 py-4 text-sm text-slate-300">
                        {{ $payoutSummary['cycle_note'] }}
                    </div>
                    <div class="rounded-2xl border border-white/8 bg-white/3 px-4 py-4 text-sm text-slate-300">
                        Current payout status: {{ $payoutSummary['status'] }}
                    </div>
                    <div class="rounded-2xl border border-white/8 bg-white/3 px-4 py-4 text-sm text-slate-300">
                        Eligible profit follows the stored profit split and only becomes available once the account reaches funded payout conditions.
                    </div>
                </div>
            </section>

            <section class="surface-card rounded-[2rem] p-6">
                <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.dashboard.payouts.requirements_title') }}</p>
                <div class="mt-6 space-y-3 text-sm text-slate-300">
                    @foreach (trans('site.dashboard.payouts.requirements') as $requirement)
                        <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">{{ $requirement }}</div>
                    @endforeach
                </div>

                <button type="button" disabled class="mt-6 w-full cursor-not-allowed rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-slate-400">
                    {{ __('site.dashboard.payouts.cta') }}
                </button>
            </section>
        </div>
    </div>
@endsection
