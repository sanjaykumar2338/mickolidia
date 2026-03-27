@extends('layouts.public')

@section('title', __('site.auth.passwords.request.title').' | '.__('site.meta.brand'))

@section('content')
    <section class="px-6 pt-12 lg:px-8 lg:pt-16">
        <div class="mx-auto max-w-3xl">
            <div class="surface-panel rounded-[2.2rem] p-8 sm:p-10">
                <span class="section-label">{{ __('site.auth.eyebrow') }}</span>
                <h1 class="mt-6 text-4xl font-semibold text-white sm:text-5xl">{{ __('site.auth.passwords.request.title') }}</h1>
                <p class="mt-5 max-w-2xl text-base leading-8 text-slate-300">{{ __('site.auth.passwords.request.copy') }}</p>

                @if (session('status'))
                    <div class="mt-6 rounded-[1.6rem] border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm leading-7 text-emerald-100">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mt-6 rounded-[1.6rem] border border-rose-400/20 bg-rose-500/10 px-5 py-4 text-sm leading-7 text-rose-100">
                        <ul class="space-y-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
                    @csrf

                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.auth.passwords.request.email') }}</span>
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            class="w-full rounded-2xl border border-white/10 bg-white/4 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-amber-400/35"
                            placeholder="trader@example.com"
                        >
                    </label>

                    <div class="flex flex-wrap gap-4">
                        <button type="submit" class="primary-cta rounded-full px-8 py-4 text-base font-semibold">
                            {{ __('site.auth.passwords.request.submit') }}
                        </button>
                        <a href="{{ route('login') }}" class="rounded-full border border-white/10 px-6 py-4 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                            {{ __('site.auth.passwords.request.back_to_login') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
