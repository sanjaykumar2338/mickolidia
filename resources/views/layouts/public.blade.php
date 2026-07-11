<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $metaTitle = trim($__env->yieldContent('title', __('site.meta.default_title')));
        $metaDescription = trim($__env->yieldContent('description', __('site.meta.description')));
        $metaImagePath = \Illuminate\Support\Facades\Lang::has('site.meta.image') ? (string) __('site.meta.image') : 'trading123.png';
        $metaImage = trim($__env->yieldContent('image', asset($metaImagePath)));
        $metaUrl = url()->current();
    @endphp
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:image" content="{{ $metaImage }}">
    <meta property="og:url" content="{{ $metaUrl }}">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $metaImage }}">
    <link rel="icon" type="image/png" href="{{ asset('newfolder/IMG_8542.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('newfolder/IMG_8542.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        $recaptchaEnabled = (bool) config('services.recaptcha.enabled', false);
        $recaptchaSiteKey = trim((string) config('services.recaptcha.site_key', ''));
        $recaptchaType = strtolower(trim((string) config('services.recaptcha.type', 'v3'))) ?: 'v3';
        $recaptchaProtectedPage = request()->routeIs('login', 'password.request', 'trial.register');
    @endphp
    @if ($recaptchaEnabled && $recaptchaSiteKey !== '' && $recaptchaProtectedPage)
        @if ($recaptchaType === 'checkbox')
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        @else
            <script src="https://www.google.com/recaptcha/api.js?render={{ urlencode($recaptchaSiteKey) }}" async defer></script>
            <script>
                document.addEventListener('submit', function (event) {
                    const form = event.target;

                    if (!(form instanceof HTMLFormElement)) {
                        return;
                    }

                    const tokenInput = form.querySelector('[data-recaptcha-token]');

                    if (!(tokenInput instanceof HTMLInputElement) || tokenInput.value !== '') {
                        return;
                    }

                    if (!window.grecaptcha || typeof window.grecaptcha.ready !== 'function') {
                        return;
                    }

                    event.preventDefault();

                    if (form.dataset.recaptchaPending === '1') {
                        return;
                    }

                    form.dataset.recaptchaPending = '1';

                    window.grecaptcha.ready(function () {
                        window.grecaptcha.execute(@json($recaptchaSiteKey), {
                            action: tokenInput.dataset.recaptchaAction || 'form_submit',
                        }).then(function (token) {
                            tokenInput.value = token;
                            delete form.dataset.recaptchaPending;

                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit();
                                return;
                            }

                            form.submit();
                        }).catch(function () {
                            delete form.dataset.recaptchaPending;
                        });
                    });
                }, true);
            </script>
        @endif
    @endif
</head>
@php
    $hasPostMainSections = ! request()->routeIs('checkout.*', 'login', 'password.*');
    $launchPromoCode = (string) config('wolforix.launch_offer.code', config('wolforix.launch_discount.code', ''));
    $launchDiscountAvailable = app(\App\Services\Pricing\ChallengePricingService::class)->publicLaunchDiscountAvailable()
        && filled($launchPromoCode);
@endphp
<body
    class="selection:bg-amber-400/30 selection:text-white"
    data-launch-promo-code="{{ session('launch_offer.applied') && $launchDiscountAvailable ? $launchPromoCode : '' }}"
>
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute inset-0 grid-pattern opacity-40"></div>
        <div class="absolute left-[8%] top-[-12rem] h-[28rem] w-[28rem] rounded-full bg-amber-400/8 blur-3xl"></div>
        <div class="absolute right-[6%] top-[8rem] h-[24rem] w-[24rem] rounded-full bg-sky-500/8 blur-3xl"></div>
    </div>

    @include('partials.public-nav')
    @include('partials.site-search')
    @if (request()->routeIs('home') && $launchDiscountAvailable && ! session()->has('launch_offer.decision'))
        @include('partials.launch-popup')
    @endif
    @include('partials.cookie-consent-banner')

    @if (session('checkout_success') || session('trial_success'))
        <div class="relative z-30 mx-auto mt-5 max-w-7xl px-6 lg:px-8">
            <div data-flash class="flash-transition rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                {{ session('checkout_success') ?? session('trial_success') }}
            </div>
        </div>
    @endif

    <main class="relative z-0 {{ $hasPostMainSections ? 'pb-0' : 'pb-32 md:pb-28' }}">
        @yield('content')
    </main>

    @unless (request()->routeIs('contact'))
        @include('partials.ai-assistant-promo')
    @endunless

    @unless (request()->routeIs('checkout.*', 'login', 'password.*'))
        @include('partials.public-payment-community')
    @endunless

    @include('partials.public-footer')
    @include('partials.back-to-top')
    @include('partials.floating-ai-assistant')
    @include('partials.fixed-disclaimer')
</body>
</html>
