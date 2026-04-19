<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('site.admin.meta_title'))</title>
    <meta name="description" content="{{ __('site.meta.description') }}">
    <link rel="icon" type="image/png" href="{{ asset('newfolder/IMG_8542.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('newfolder/IMG_8542.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="selection:bg-amber-400/30 selection:text-white">
    @php
        $adminAuthenticated = session(\App\Http\Middleware\AdminBasicAuth::SESSION_KEY, false) === true;
    @endphp

    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute inset-0 grid-pattern opacity-25"></div>
        <div class="absolute left-[8%] top-[-8rem] h-[24rem] w-[24rem] rounded-full bg-sky-500/10 blur-3xl"></div>
        <div class="absolute right-[8%] top-[5rem] h-[20rem] w-[20rem] rounded-full bg-amber-400/10 blur-3xl"></div>
    </div>

    <header class="border-b border-white/5 bg-slate-950/78 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-4 lg:px-8">
            <div class="flex items-center gap-3">
                <a href="{{ route('home') }}" class="flex h-11 w-11 items-center justify-center">
                    <img src="{{ asset('newfolder/IMG_8542.png') }}" alt="Wolforix" class="h-full w-full object-contain">
                </a>
                <div>
                    <p class="inline-flex items-start text-sm font-semibold tracking-[0.28em] text-amber-300">
                        <span>WOLFORIX</span>
                        <span class="ml-1 text-[0.58em] leading-none tracking-normal text-amber-200">®</span>
                    </p>
                    <p class="mt-1 text-xs text-slate-400">{{ __('site.admin.header_label') }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @if ($adminAuthenticated)
                    <a href="{{ route('admin.clients.index') }}" class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                        {{ __('site.admin.clients.title') }}
                    </a>
                    <a href="{{ route('admin.reviews.index') }}" class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                        Review Emails
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                            {{ __('site.admin.logout') }}
                        </button>
                    </form>
                @endif
                <a href="{{ route('home') }}" class="rounded-full border border-amber-400/20 bg-amber-400/10 px-4 py-2 text-sm font-semibold text-amber-100 transition hover:border-amber-300/40 hover:bg-amber-400/16">
                    {{ __('site.admin.back_to_site') }}
                </a>
            </div>
        </div>
    </header>

    <main class="px-6 py-10 lg:px-8">
        <div class="mx-auto max-w-7xl">
            @if (session('status'))
                <div class="mb-6 rounded-[1.6rem] border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm font-medium text-emerald-100">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 rounded-[1.6rem] border border-rose-400/20 bg-rose-500/10 px-5 py-4 text-sm font-medium text-rose-100">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</body>
</html>
