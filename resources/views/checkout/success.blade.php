@extends('layouts.public')

@section('title', __('site.checkout.success.title').' | '.__('site.meta.brand'))

@section('content')
    <section class="px-6 pb-16 pt-10 lg:px-8 lg:pt-14">
        <div class="mx-auto max-w-4xl">
            <div class="surface-panel rounded-[2rem] p-8">
                <span class="section-label">{{ __('site.checkout.success.eyebrow') }}</span>
                <h1 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ __('site.checkout.success.title') }}</h1>
                <p class="mt-4 text-base leading-8 text-slate-300">
                    {{ $order->isPaid() ? __('site.checkout.success.description') : __('site.checkout.success.pending_description') }}
                </p>

                <div class="mt-8 grid gap-4 md:grid-cols-2">
                    <div class="surface-card rounded-[1.8rem] p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('site.checkout.success.plan') }}</p>
                        <p class="mt-3 text-xl font-semibold text-white">{{ __('site.home.challenge_selector.types.'.$order->challenge_type.'.label') }} / {{ (int) ($order->account_size / 1000) }}K</p>
                    </div>
                    <div class="surface-card rounded-[1.8rem] p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('site.checkout.success.amount') }}</p>
                        <p class="mt-3 text-xl font-semibold text-white">{{ number_format((float) $order->final_price, 2) }} {{ $order->currency }}</p>
                    </div>
                    <div class="surface-card rounded-[1.8rem] p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('site.checkout.success.provider') }}</p>
                        <p class="mt-3 text-xl font-semibold text-white">{{ ucfirst($order->payment_provider) }}</p>
                    </div>
                    <div class="surface-card rounded-[1.8rem] p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('site.checkout.success.order_number') }}</p>
                        <p class="mt-3 text-xl font-semibold text-white">{{ $order->order_number }}</p>
                    </div>
                </div>

                <div class="mt-8 rounded-[1.8rem] border border-emerald-400/18 bg-emerald-500/10 p-5 text-sm leading-7 text-emerald-50">
                    {{ __('site.checkout.success.next_steps') }}
                </div>

                <div class="mt-8 flex flex-wrap gap-4">
                    <a href="{{ route('dashboard.accounts') }}" class="rounded-full border border-white/10 px-6 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                        {{ __('site.checkout.success.open_dashboard') }}
                    </a>
                    <a href="{{ route('home') }}" class="rounded-full border border-white/10 px-6 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                        {{ __('site.checkout.success.back_home') }}
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
