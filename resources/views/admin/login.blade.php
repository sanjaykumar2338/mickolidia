@extends('admin.layout')

@section('title', __('site.admin.login.title').' | '.__('site.meta.brand'))

@section('content')
    <div class="mx-auto max-w-lg">
        <div class="surface-panel rounded-[2rem] p-6 sm:p-8">
            <span class="section-label">{{ __('site.admin.login.eyebrow') }}</span>
            <h1 class="mt-5 text-3xl font-semibold text-white sm:text-4xl">{{ __('site.admin.login.title') }}</h1>
            <p class="mt-4 text-base leading-8 text-slate-300">{{ __('site.admin.login.description') }}</p>

            @if (session('status'))
                <div class="mt-6 rounded-[1.35rem] border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-6 rounded-[1.35rem] border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                    {{ $errors->first('username') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.store') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="admin_username" class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('site.admin.login.username') }}</label>
                    <input
                        id="admin_username"
                        name="username"
                        type="text"
                        autocomplete="username"
                        value="{{ old('username') }}"
                        class="mt-2 w-full rounded-[1.15rem] border border-white/10 bg-white/4 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-amber-300/30 focus:outline-none focus:ring-2 focus:ring-amber-300/10"
                    >
                </div>

                <div>
                    <label for="admin_password" class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{{ __('site.admin.login.password') }}</label>
                    <input
                        id="admin_password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        class="mt-2 w-full rounded-[1.15rem] border border-white/10 bg-white/4 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-amber-300/30 focus:outline-none focus:ring-2 focus:ring-amber-300/10"
                    >
                </div>

                <button type="submit" class="primary-cta mt-2 rounded-full px-6 py-3 text-sm font-semibold">
                    {{ __('site.admin.login.submit') }}
                </button>
            </form>
        </div>
    </div>
@endsection
