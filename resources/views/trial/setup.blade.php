@extends('layouts.public')

@section('title', __('site.trial.setup.title').' | '.__('site.meta.brand'))

@section('content')
    <section class="px-6 pt-12 lg:px-8">
        <div class="mx-auto max-w-6xl">
            <span class="section-label">{{ __('site.trial.eyebrow') }}</span>
            <div class="mt-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-4xl font-semibold text-white sm:text-5xl">{{ __('site.trial.setup.title') }}</h1>
                    <p class="mt-4 max-w-3xl text-base leading-8 text-slate-300">{{ __('site.trial.setup.description') }}</p>
                </div>
                <div class="gold-pill rounded-full px-4 py-2 text-sm font-medium">
                    {{ $trialAccount->account_reference }}
                </div>
            </div>

            @if (session('trial_success'))
                <div class="mt-8 rounded-[1.8rem] border border-emerald-400/18 bg-emerald-500/10 p-5 text-sm leading-7 text-emerald-50">
                    {{ session('trial_success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-8 rounded-[1.8rem] border border-rose-400/20 bg-rose-500/10 px-4 py-4 text-sm text-rose-100">
                    <ul class="space-y-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-10 grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
                <aside class="surface-panel rounded-[2rem] p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">{{ __('site.trial.setup.process_label') }}</p>
                    <div class="mt-6 space-y-4">
                        @foreach (trans('site.trial.setup.steps') as $index => $step)
                            <div class="rounded-2xl border border-white/6 bg-black/15 px-4 py-4">
                                <div class="flex items-start gap-4">
                                    <span class="inline-flex h-8 w-8 flex-none items-center justify-center rounded-full border border-amber-300/25 bg-amber-300/12 text-sm font-semibold text-amber-100">
                                        {{ $index + 1 }}
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-white">{{ $step['title'] }}</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-300">{{ $step['body'] }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </aside>

                <div class="space-y-6">
                    <section class="surface-card rounded-[2rem] p-6 sm:p-8">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200">{{ __('site.trial.setup.step_two_label') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-white">{{ __('site.trial.setup.open_demo_title') }}</h2>
                        <p class="mt-4 text-sm leading-7 text-slate-300">{{ __('site.trial.setup.open_demo_copy') }}</p>
                        <ul class="mt-5 space-y-3 text-sm text-slate-300">
                            @foreach (trans('site.trial.setup.important_items') as $item)
                                <li class="rounded-2xl border border-white/6 bg-black/15 px-4 py-3">{{ $item }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ $demoRegistrationUrl }}" target="_blank" rel="noopener" class="primary-cta mt-6 inline-flex rounded-full px-8 py-4 text-base font-semibold">
                            {{ __('site.trial.setup.open_demo_button') }}
                        </a>
                    </section>

                    @include('trial.partials.mt5-connector', ['connector' => $connector, 'compact' => false])

                    <section class="surface-panel rounded-[2rem] p-6 sm:p-8">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">{{ __('site.trial.setup.step_three_label') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-white">{{ __('site.trial.connector.status_title') }}</h2>
                        <p class="mt-4 text-sm leading-7 text-slate-300">{{ __('site.trial.connector.waiting_sync') }}</p>
                        <form method="POST" action="{{ route('trial.confirm-demo') }}" class="mt-6">
                            @csrf
                            <button type="submit" class="primary-cta w-full justify-center rounded-full px-8 py-4 text-base font-semibold">
                                {{ __('site.trial.setup.continue_button') }}
                            </button>
                        </form>
                    </section>

                    <section class="rounded-[2rem] border border-sky-400/18 bg-sky-500/10 p-6 text-sm leading-7 text-sky-50">
                        <p class="font-semibold">{{ __('site.trial.setup.help_title') }}</p>
                        <p class="mt-2">
                            {{ __('site.trial.setup.help_copy') }}
                            <a href="mailto:{{ config('wolforix.support.email') }}" class="font-semibold text-amber-200 underline decoration-amber-300/40 underline-offset-4">{{ config('wolforix.support.email') }}</a>.
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </section>
@endsection
