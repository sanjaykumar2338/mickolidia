@extends('layouts.public')

@section('title', __('site.trial.register.title').' | '.__('site.meta.brand'))

@section('content')
    <section class="px-6 pt-12 lg:px-8">
        <div class="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[0.9fr_1.1fr]">
            <div>
                <span class="section-label">{{ __('site.trial.eyebrow') }}</span>
                <h1 class="mt-5 text-4xl font-semibold text-white sm:text-5xl">{{ __('site.trial.register.title') }}</h1>
                <p class="mt-4 max-w-2xl text-base leading-8 text-slate-300">{{ __('site.trial.register.description') }}</p>

                <div class="mt-8 surface-card rounded-[2rem] p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">{{ __('site.trial.register.what_you_get_title') }}</p>
                    <ul class="mt-5 space-y-3 text-sm text-slate-300">
                        <li class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">{{ __('site.trial.register.balance_line', ['amount' => '$'.number_format($startingBalance, 0)]) }}</li>
                        <li class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">{{ __('site.trial.register.take_profit_line', ['percent' => $displayRules['profit_target'] ?? 8]) }}</li>
                        <li class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">{{ __('site.trial.register.minimum_days_line', ['days' => $displayRules['minimum_trading_days'] ?? 3]) }}</li>
                        <li class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">{{ __('site.trial.register.markets_line', ['markets' => implode(', ', $allowedSymbols)]) }}</li>
                        <li class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">{{ __('site.trial.register.restrictions_line') }}</li>
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

                <form method="POST" action="{{ route('trial.store') }}" class="space-y-5">
                    @csrf

                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.trial.register.email') }}</span>
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                            placeholder="trader@example.com"
                            required
                        >
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.trial.register.password') }}</span>
                        <input
                            type="password"
                            name="password"
                            class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                            placeholder="{{ __('site.trial.register.password_placeholder') }}"
                            required
                        >
                    </label>

                    <button type="submit" class="primary-cta w-full rounded-full px-8 py-4 text-base font-semibold">
                        {{ __('site.trial.register.submit') }}
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection
