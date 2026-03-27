@extends('layouts.public')

@section('title', __('site.auth.title').' | '.__('site.meta.brand'))

@php
    $intendedUrl = (string) session('url.intended', '');
    $returningToCheckout = str_contains($intendedUrl, route('checkout.show', [], false));
    $loginErrors = $errors->getBag('login');
    $registerErrors = $errors->getBag('register');
@endphp

@section('content')
    <section class="px-6 pt-12 lg:px-8 lg:pt-16">
        <div class="mx-auto max-w-7xl">
            <div class="grid gap-8 xl:grid-cols-[0.86fr_1.14fr]">
                <div class="surface-panel rounded-[2.2rem] p-8 sm:p-10">
                    <span class="section-label">{{ __('site.auth.eyebrow') }}</span>
                    <h1 class="mt-6 max-w-3xl text-4xl font-semibold text-white sm:text-5xl">{{ __('site.auth.title') }}</h1>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-slate-300">{{ __('site.auth.description') }}</p>

                    <div class="mt-8 rounded-[1.8rem] border border-amber-400/18 bg-amber-400/10 p-5 text-sm leading-7 text-amber-50">
                        {{ __('site.auth.notice') }}
                    </div>

                    @if ($returningToCheckout)
                        <div class="mt-5 rounded-[1.6rem] border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm leading-7 text-emerald-100">
                            {{ __('site.checkout.secure_badge') }}: {{ __('site.auth.description') }}
                        </div>
                    @endif

                    <div class="mt-8 flex flex-wrap gap-4">
                        <a href="{{ route('home') }}" class="primary-cta rounded-full px-8 py-4 text-base font-semibold">
                            {{ __('site.auth.home_action') }}
                        </a>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="surface-panel rounded-[2rem] p-6 sm:p-7">
                        <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.auth.login.title') }}</p>
                        <p class="mt-3 text-sm leading-7 text-slate-400">{{ __('site.auth.login.copy') }}</p>

                        @if (session('status'))
                            <div class="mt-5 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-4 text-sm text-emerald-100">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if ($loginErrors->any())
                            <div class="mt-5 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-4 text-sm text-rose-100">
                                <ul class="space-y-2">
                                    @foreach ($loginErrors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
                            @csrf

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.auth.login.email') }}</span>
                                <input
                                    type="email"
                                    name="login_email"
                                    value="{{ old('login_email') }}"
                                    class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                    placeholder="trader@example.com"
                                >
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.auth.login.password') }}</span>
                                <input
                                    type="password"
                                    name="login_password"
                                    class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                >
                            </label>

                            <div class="flex justify-end">
                                <a href="{{ route('password.request') }}" class="text-sm font-medium text-amber-200 transition hover:text-amber-100">
                                    {{ __('site.auth.login.forgot_password') }}
                                </a>
                            </div>

                            <label class="flex items-center gap-3 text-sm text-slate-300">
                                <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="h-4 w-4 rounded border-white/20 bg-black/40 text-amber-400 focus:ring-amber-300">
                                <span>{{ __('site.auth.login.remember') }}</span>
                            </label>

                            <button type="submit" class="primary-cta w-full rounded-full px-8 py-4 text-base font-semibold">
                                {{ __('site.auth.login.submit') }}
                            </button>
                        </form>
                    </section>

                    <section class="surface-card rounded-[2rem] p-6 sm:p-7">
                        <p class="text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.auth.register.title') }}</p>
                        <p class="mt-3 text-sm leading-7 text-slate-400">{{ __('site.auth.register.copy') }}</p>

                        @if ($registerErrors->any())
                            <div class="mt-5 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-4 text-sm text-rose-100">
                                <ul class="space-y-2">
                                    @foreach ($registerErrors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-5">
                            @csrf

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.auth.register.name') }}</span>
                                <input
                                    type="text"
                                    name="register_name"
                                    value="{{ old('register_name') }}"
                                    class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                    placeholder="{{ __('site.auth.register.name') }}"
                                >
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.auth.register.email') }}</span>
                                <input
                                    type="email"
                                    name="register_email"
                                    value="{{ old('register_email') }}"
                                    class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                    placeholder="trader@example.com"
                                >
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.auth.register.password') }}</span>
                                <input
                                    type="password"
                                    name="register_password"
                                    class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                >
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.auth.register.password_confirmation') }}</span>
                                <input
                                    type="password"
                                    name="register_password_confirmation"
                                    class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                                >
                            </label>

                            <button type="submit" class="ghost-cta w-full rounded-full px-8 py-4 text-base font-semibold">
                                {{ __('site.auth.register.submit') }}
                            </button>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </section>
@endsection
