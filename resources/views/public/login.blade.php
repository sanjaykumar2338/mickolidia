@extends('layouts.public')

@section('title', __('site.auth.title').' | '.__('site.meta.brand'))

@php
    $intendedUrl = (string) session('url.intended', '');
    $returningToCheckout = str_contains($intendedUrl, route('checkout.show', [], false));
    $loginErrors = $errors->getBag('login');
    $registerErrors = $errors->getBag('register');
    $socialProviders = [
        [
            'key' => 'google',
            'label' => __('site.auth.login.social_google'),
            'route_name' => 'social.redirect',
        ],
        [
            'key' => 'facebook',
            'label' => __('site.auth.login.social_facebook'),
            'route_name' => 'social.redirect',
        ],
        [
            'key' => 'apple',
            'label' => __('site.auth.login.social_apple'),
            'route_name' => 'social.redirect',
        ],
    ];

    $socialProviders = array_map(static function (array $provider): array {
        $provider['configured'] = filled(config('services.'.$provider['key'].'.client_id'))
            && filled(config('services.'.$provider['key'].'.client_secret'))
            && filled(config('services.'.$provider['key'].'.redirect_uri'));
        $provider['href'] = $provider['configured'] && Route::has($provider['route_name'])
            ? route($provider['route_name'], ['provider' => $provider['key']])
            : null;

        return $provider;
    }, $socialProviders);

    $hasAvailableSocialProvider = collect($socialProviders)->contains(static fn (array $provider): bool => filled($provider['href']));
@endphp

@section('content')
    <section class="px-6 pt-6 lg:px-8 lg:pt-8">
        <div class="mx-auto max-w-7xl">
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="surface-panel rounded-[2rem] p-6 sm:p-7">
                    <span class="section-label">{{ __('site.auth.eyebrow') }}</span>
                    <p class="mt-4 text-sm font-semibold uppercase tracking-[0.26em] text-amber-300">{{ __('site.auth.login.title') }}</p>
                    <h1 class="mt-5 text-3xl font-semibold text-white sm:text-[2.2rem]">{{ __('site.auth.title') }}</h1>

                    @if ($returningToCheckout)
                        <div class="mt-4 rounded-[1.4rem] border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm leading-7 text-emerald-100">
                            {{ __('site.checkout.secure_badge') }}
                        </div>
                    @endif

                    <p class="mt-4 text-sm leading-7 text-slate-400">{{ __('site.auth.login.copy') }}</p>

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

                    <div class="mt-7">
                        <div class="flex items-center gap-3">
                            <span class="h-px flex-1 bg-white/10"></span>
                            <span class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">{{ __('site.auth.login.social_divider') }}</span>
                            <span class="h-px flex-1 bg-white/10"></span>
                        </div>

                        <div class="mt-4 space-y-3">
                            @foreach ($socialProviders as $provider)
                                @php($providerUnavailable = blank($provider['href']))

                                @if ($providerUnavailable)
                                    <button
                                        type="button"
                                        disabled
                                        aria-disabled="true"
                                        class="social-auth-button social-auth-button-disabled"
                                    >
                                        <span class="social-auth-button-icon">
                                            @if ($provider['key'] === 'google')
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5" aria-hidden="true">
                                                    <path fill="#EA4335" d="M12 10.2v3.92h5.45c-.24 1.26-.95 2.33-2.01 3.05l3.24 2.51c1.89-1.74 2.98-4.29 2.98-7.32 0-.72-.06-1.42-.18-2.09H12Z"/>
                                                    <path fill="#34A853" d="M12 22c2.7 0 4.96-.89 6.62-2.41l-3.24-2.51c-.9.6-2.05.96-3.38.96-2.6 0-4.8-1.76-5.59-4.12H3.06v2.59A9.99 9.99 0 0 0 12 22Z"/>
                                                    <path fill="#4A90E2" d="M6.41 13.92A5.98 5.98 0 0 1 6.1 12c0-.67.12-1.31.31-1.92V7.49H3.06A9.99 9.99 0 0 0 2 12c0 1.61.39 3.13 1.06 4.51l3.35-2.59Z"/>
                                                    <path fill="#FBBC05" d="M12 5.96c1.47 0 2.79.51 3.83 1.5l2.87-2.87C16.95 2.96 14.69 2 12 2 8.09 2 4.72 4.24 3.06 7.49l3.35 2.59c.79-2.36 2.99-4.12 5.59-4.12Z"/>
                                                </svg>
                                            @elseif ($provider['key'] === 'facebook')
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5" aria-hidden="true">
                                                    <path fill="#1877F2" d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07c0 6.03 4.39 11.03 10.13 11.93v-8.44H7.08v-3.49h3.05V9.41c0-3.03 1.79-4.7 4.54-4.7 1.31 0 2.69.24 2.69.24v2.97h-1.52c-1.5 0-1.97.94-1.97 1.89v2.27h3.35l-.54 3.49h-2.81V24C19.61 23.1 24 18.1 24 12.07Z"/>
                                                </svg>
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                                                    <path d="M16.37 12.33c.02 2.61 2.29 3.48 2.31 3.49-.02.06-.36 1.25-1.2 2.48-.72 1.06-1.47 2.11-2.65 2.13-1.16.02-1.53-.69-2.86-.69-1.33 0-1.74.67-2.83.71-1.13.04-1.99-1.14-2.72-2.19-1.5-2.17-2.64-6.14-1.11-8.82.76-1.33 2.12-2.17 3.59-2.19 1.12-.02 2.17.75 2.85.75.68 0 1.95-.93 3.29-.79.56.02 2.13.23 3.14 1.71-.08.05-1.87 1.09-1.85 3.41ZM14.78 5.52c.6-.73 1-1.74.89-2.75-.87.04-1.93.58-2.56 1.3-.56.64-1.05 1.67-.92 2.66.97.08 1.98-.49 2.59-1.21Z"/>
                                                </svg>
                                            @endif
                                        </span>
                                        <span class="flex-1 text-left">{{ $provider['label'] }}</span>
                                        <span class="rounded-full border border-white/8 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                            {{ __('site.auth.login.social_unavailable_badge') }}
                                        </span>
                                    </button>
                                @else
                                    <a href="{{ $provider['href'] }}" class="social-auth-button">
                                        <span class="social-auth-button-icon">
                                            @if ($provider['key'] === 'google')
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5" aria-hidden="true">
                                                    <path fill="#EA4335" d="M12 10.2v3.92h5.45c-.24 1.26-.95 2.33-2.01 3.05l3.24 2.51c1.89-1.74 2.98-4.29 2.98-7.32 0-.72-.06-1.42-.18-2.09H12Z"/>
                                                    <path fill="#34A853" d="M12 22c2.7 0 4.96-.89 6.62-2.41l-3.24-2.51c-.9.6-2.05.96-3.38.96-2.6 0-4.8-1.76-5.59-4.12H3.06v2.59A9.99 9.99 0 0 0 12 22Z"/>
                                                    <path fill="#4A90E2" d="M6.41 13.92A5.98 5.98 0 0 1 6.1 12c0-.67.12-1.31.31-1.92V7.49H3.06A9.99 9.99 0 0 0 2 12c0 1.61.39 3.13 1.06 4.51l3.35-2.59Z"/>
                                                    <path fill="#FBBC05" d="M12 5.96c1.47 0 2.79.51 3.83 1.5l2.87-2.87C16.95 2.96 14.69 2 12 2 8.09 2 4.72 4.24 3.06 7.49l3.35 2.59c.79-2.36 2.99-4.12 5.59-4.12Z"/>
                                                </svg>
                                            @elseif ($provider['key'] === 'facebook')
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5" aria-hidden="true">
                                                    <path fill="#1877F2" d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07c0 6.03 4.39 11.03 10.13 11.93v-8.44H7.08v-3.49h3.05V9.41c0-3.03 1.79-4.7 4.54-4.7 1.31 0 2.69.24 2.69.24v2.97h-1.52c-1.5 0-1.97.94-1.97 1.89v2.27h3.35l-.54 3.49h-2.81V24C19.61 23.1 24 18.1 24 12.07Z"/>
                                                </svg>
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                                                    <path d="M16.37 12.33c.02 2.61 2.29 3.48 2.31 3.49-.02.06-.36 1.25-1.2 2.48-.72 1.06-1.47 2.11-2.65 2.13-1.16.02-1.53-.69-2.86-.69-1.33 0-1.74.67-2.83.71-1.13.04-1.99-1.14-2.72-2.19-1.5-2.17-2.64-6.14-1.11-8.82.76-1.33 2.12-2.17 3.59-2.19 1.12-.02 2.17.75 2.85.75.68 0 1.95-.93 3.29-.79.56.02 2.13.23 3.14 1.71-.08.05-1.87 1.09-1.85 3.41ZM14.78 5.52c.6-.73 1-1.74.89-2.75-.87.04-1.93.58-2.56 1.3-.56.64-1.05 1.67-.92 2.66.97.08 1.98-.49 2.59-1.21Z"/>
                                                </svg>
                                            @endif
                                        </span>
                                        <span class="flex-1 text-left">{{ $provider['label'] }}</span>
                                    </a>
                                @endif
                            @endforeach
                        </div>

                        @unless ($hasAvailableSocialProvider)
                            <p class="mt-4 text-xs leading-6 text-slate-500">
                                {{ __('site.auth.login.social_setup_notice') }}
                            </p>
                        @endunless
                    </div>
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

            <div class="mt-6 grid gap-6 xl:grid-cols-[1.08fr_0.92fr]">
                <div class="surface-panel rounded-[2rem] p-6 sm:p-7">
                    <p class="text-sm leading-7 text-slate-300">{{ __('site.auth.description') }}</p>

                    <div class="mt-4 rounded-[1.4rem] border border-amber-400/18 bg-amber-400/10 px-4 py-4 text-sm leading-7 text-amber-50">
                        {{ __('site.auth.notice') }}
                    </div>
                </div>

                <div class="flex flex-wrap items-start gap-4 rounded-[2rem] border border-white/8 bg-white/4 p-6">
                    <a href="{{ route('home') }}" class="primary-cta rounded-full px-8 py-4 text-base font-semibold">
                        {{ __('site.auth.home_action') }}
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
