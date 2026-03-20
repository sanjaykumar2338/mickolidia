@extends('layouts.public')

@section('title', __('site.meta.default_title'))

@section('content')
    <section class="px-6 pt-10 lg:px-8 lg:pt-14">
        <div class="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-[1.05fr_minmax(0,0.95fr)]">
            <div>
                <span class="section-label">{{ __('site.home.eyebrow') }}</span>
                <h1 class="mt-6 max-w-4xl text-4xl font-semibold leading-tight text-white sm:text-5xl lg:text-6xl">
                    {{ __('site.home.title') }}
                </h1>
                <p class="mt-6 max-w-3xl text-base leading-8 text-slate-300 sm:text-lg">
                    {{ __('site.home.description') }}
                </p>

                <div class="mt-6 flex flex-wrap gap-3">
                    @foreach (trans('site.home.badges') as $badge)
                        <span class="gold-pill rounded-full px-4 py-2 text-sm font-medium">{{ $badge }}</span>
                    @endforeach
                </div>

                <div class="mt-8 flex flex-wrap gap-4">
                    <a href="#plans" class="rounded-full bg-amber-400 px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-300">
                        {{ __('site.home.primary_cta') }}
                    </a>
                    <a href="{{ route('dashboard') }}" class="rounded-full border border-white/10 px-6 py-3 text-sm font-semibold text-white transition hover:border-white/25 hover:bg-white/6">
                        {{ __('site.home.secondary_cta') }}
                    </a>
                </div>

                <div class="mt-10 surface-panel rounded-[2rem] p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-amber-300">{{ __('site.home.hero_panel.title') }}</p>
                            <p class="mt-2 text-sm leading-7 text-slate-400">{{ __('site.home.hero_panel.caption') }}</p>
                        </div>
                        <div class="rounded-full border border-sky-400/20 bg-sky-500/10 px-4 py-2 text-sm text-sky-100">
                            {{ __('site.home.hero_panel.status') }}
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        @foreach (trans('site.home.hero_panel.items') as $item)
                            <div class="surface-card rounded-2xl px-4 py-4 text-sm text-slate-200">{{ $item }}</div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="relative">
                <div class="surface-panel overflow-hidden rounded-[2rem] border border-white/8 p-4">
                    <div class="overflow-hidden rounded-[1.6rem] border border-white/6 bg-black/40">
                        <img src="{{ asset('branding/659E27F6-E56C-4991-8383-F0E0B8FA9FBB.png') }}" alt="Wolforix branding reference" class="h-full w-full object-cover">
                    </div>
                </div>

                <div class="absolute -bottom-5 left-5 right-5 surface-card rounded-[1.8rem] p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.home.image_caption') }}</p>
                    <p class="mt-3 text-sm leading-7 text-slate-300">{{ __('site.home.image_copy') }}</p>
                </div>
            </div>
        </div>

        <div class="mx-auto mt-14 grid max-w-7xl gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach (trans('site.home.metrics') as $metric)
                <div class="surface-card rounded-3xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{{ $metric['label'] }}</p>
                    <p class="mt-4 text-3xl font-semibold text-white">{{ $metric['value'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section id="plans" class="px-6 pt-20 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <span class="section-label">{{ __('site.home.plans.eyebrow') }}</span>
            <div class="mt-5 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <h2 class="text-3xl font-semibold text-white sm:text-4xl">{{ __('site.home.plans.title') }}</h2>
                    <p class="mt-4 text-base leading-8 text-slate-300">{{ __('site.home.plans.description') }}</p>
                </div>
                <div class="gold-pill rounded-full px-4 py-2 text-sm font-medium">
                    {{ __('site.home.plans.badge') }}
                </div>
            </div>

            <div class="mt-10 grid gap-5 xl:grid-cols-4 md:grid-cols-2">
                @foreach ($plans as $plan)
                    <article class="{{ $plan['slug'] === 'wolf-50000' ? 'border-amber-400/28 bg-amber-400/8' : 'border-white/8 bg-white/3' }} rounded-[2rem] border p-6 shadow-xl shadow-slate-950/15">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold tracking-[0.24em] text-amber-300">{{ $plan['name'] }}</p>
                                <p class="mt-2 text-4xl font-semibold text-white">€{{ number_format($plan['account_size']) }}</p>
                            </div>
                            <span class="rounded-full border border-white/8 bg-black/20 px-3 py-1 text-xs uppercase tracking-[0.24em] text-slate-300">
                                {{ __('site.home.plans.entry_fee') }} €{{ number_format($plan['entry_fee'], 0) }}
                            </span>
                        </div>

                        <dl class="mt-6 space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-400">{{ __('site.home.plans.profit_target') }}</dt>
                                <dd class="font-semibold text-white">{{ number_format($plan['profit_target'], 0) }}%</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-400">{{ __('site.home.plans.daily_loss') }}</dt>
                                <dd class="font-semibold text-white">{{ number_format($plan['daily_loss_limit'], 0) }}%</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-400">{{ __('site.home.plans.max_loss') }}</dt>
                                <dd class="font-semibold text-white">{{ number_format($plan['max_loss_limit'], 0) }}%</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-400">{{ __('site.home.plans.steps') }}</dt>
                                <dd class="font-semibold text-white">{{ $plan['steps'] }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-400">{{ __('site.home.plans.profit_share') }}</dt>
                                <dd class="font-semibold text-white">{{ number_format($plan['profit_share'], 0) }}%</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-400">{{ __('site.home.plans.first_payout') }}</dt>
                                <dd class="font-semibold text-white">{{ $plan['first_payout_days'] }} {{ __('site.home.days') }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-400">{{ __('site.home.plans.minimum_days') }}</dt>
                                <dd class="font-semibold text-white">{{ $plan['minimum_trading_days'] }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="px-6 pt-20 lg:px-8">
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.95fr_1.05fr]">
            <div>
                <span class="section-label">{{ __('site.home.foundation.eyebrow') }}</span>
                <h2 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ __('site.home.foundation.title') }}</h2>
                <p class="mt-4 text-base leading-8 text-slate-300">{{ __('site.home.foundation.description') }}</p>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                @foreach (trans('site.home.foundation.cards') as $card)
                    <div class="surface-card rounded-[2rem] p-6">
                        <p class="text-lg font-semibold text-white">{{ $card['title'] }}</p>
                        <p class="mt-3 text-sm leading-7 text-slate-400">{{ $card['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="px-6 pt-20 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <span class="section-label">{{ __('site.home.workflow.eyebrow') }}</span>
            <h2 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ __('site.home.workflow.title') }}</h2>
            <div class="mt-10 grid gap-5 lg:grid-cols-3">
                @foreach (trans('site.home.workflow.items') as $index => $item)
                    <div class="surface-panel rounded-[2rem] p-6">
                        <div class="flex items-center gap-4">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full border border-amber-400/20 bg-amber-400/10 text-sm font-semibold text-amber-100">
                                {{ $index + 1 }}
                            </span>
                            <h3 class="text-xl font-semibold text-white">{{ $item['title'] }}</h3>
                        </div>
                        <p class="mt-5 text-sm leading-7 text-slate-300">{{ $item['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="checkout" class="px-6 pt-20 lg:px-8">
        <div class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[0.95fr_1.05fr]">
            <div>
                <span class="section-label">{{ __('site.checkout.eyebrow') }}</span>
                <h2 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ __('site.checkout.title') }}</h2>
                <p class="mt-4 text-base leading-8 text-slate-300">{{ __('site.checkout.description') }}</p>

                <div class="mt-8 surface-card rounded-[2rem] p-6">
                    <h3 class="text-lg font-semibold text-white">{{ __('site.checkout.supporting_title') }}</h3>
                    <p class="mt-3 text-sm leading-7 text-slate-400">{{ __('site.checkout.supporting_copy') }}</p>
                    <ul class="mt-5 space-y-3 text-sm text-slate-300">
                        @foreach (trans('site.checkout.helper_points') as $point)
                            <li class="rounded-2xl border border-white/6 bg-white/3 px-4 py-3">{{ $point }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="surface-panel rounded-[2rem] p-6 sm:p-8">
                @if ($errors->any())
                    <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-4 text-sm text-rose-100">
                        <ul class="space-y-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('challenge.checkout.store') }}" class="space-y-5">
                    @csrf

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.full_name') }}</span>
                            <input
                                type="text"
                                name="full_name"
                                value="{{ old('full_name') }}"
                                class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                placeholder="{{ __('site.checkout.full_name') }}"
                            >
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.email') }}</span>
                            <input
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                placeholder="trader@example.com"
                            >
                        </label>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.plan') }}</span>
                            <select
                                name="plan"
                                class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition focus:border-amber-400/35"
                            >
                                <option value="">{{ __('site.checkout.select_plan') }}</option>
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan['slug'] }}" @selected(old('plan') === $plan['slug'])>
                                        {{ $plan['name'] }} - €{{ number_format($plan['account_size']) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.checkout.platform') }}</span>
                            <input
                                type="text"
                                value="{{ __('site.checkout.platform_value') }}"
                                readonly
                                class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-slate-300 outline-none"
                            >
                        </label>
                    </div>

                    <label class="flex items-start gap-3 rounded-2xl border border-amber-400/18 bg-amber-400/10 px-4 py-4">
                        <input
                            type="checkbox"
                            name="accept_terms"
                            value="1"
                            @checked(old('accept_terms'))
                            class="mt-1 h-4 w-4 rounded border-white/20 bg-black/40 text-amber-400 focus:ring-amber-300"
                        >
                        <span class="text-sm leading-7 text-amber-50">{{ __('site.checkout.agreement') }}</span>
                    </label>

                    <div class="grid gap-3 md:grid-cols-2">
                        <button type="submit" class="rounded-full bg-amber-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-300">
                            {{ __('site.checkout.submit') }}
                        </button>
                        <button type="button" disabled class="cursor-not-allowed rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-slate-400">
                            {{ __('site.checkout.buttons.stripe') }}
                        </button>
                    </div>

                    <button type="button" disabled class="w-full cursor-not-allowed rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-slate-400">
                        {{ __('site.checkout.buttons.paypal') }}
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection
