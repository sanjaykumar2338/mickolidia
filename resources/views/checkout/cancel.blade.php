@extends('layouts.public')

@section('title', __('site.checkout.cancel.title').' | '.__('site.meta.brand'))

@section('content')
    <section class="px-6 pb-16 pt-10 lg:px-8 lg:pt-14">
        <div class="mx-auto max-w-4xl">
            <div class="surface-panel rounded-[2rem] p-8">
                <span class="section-label">{{ __('site.checkout.cancel.eyebrow') }}</span>
                <h1 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ __('site.checkout.cancel.title') }}</h1>
                <p class="mt-4 text-base leading-8 text-slate-300">{{ __('site.checkout.cancel.description') }}</p>

                <div class="mt-8 rounded-[1.8rem] border border-white/8 bg-white/3 p-5 text-sm text-slate-300">
                    <p><strong class="text-white">{{ __('site.checkout.cancel.order_number') }}:</strong> {{ $order->order_number }}</p>
                    <p class="mt-3"><strong class="text-white">{{ __('site.checkout.cancel.plan') }}:</strong> {{ __('site.home.challenge_selector.types.'.$order->challenge_type.'.label') }} / {{ (int) ($order->account_size / 1000) }}K</p>
                    <p class="mt-3"><strong class="text-white">{{ __('site.checkout.cancel.amount') }}:</strong> {{ number_format((float) $order->final_price, 2) }} {{ $order->currency }}</p>
                </div>

                <div class="mt-8 flex flex-wrap gap-4">
                    <a href="{{ route('checkout.show', ['order' => $order->order_number]) }}" class="primary-cta rounded-full px-8 py-4 text-base font-semibold">
                        {{ __('site.checkout.cancel.retry') }}
                    </a>
                    <a href="{{ route('home') }}#plans" class="rounded-full border border-white/10 px-6 py-4 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                        {{ __('site.checkout.cancel.back_to_plans') }}
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
