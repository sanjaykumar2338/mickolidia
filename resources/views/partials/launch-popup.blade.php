@php
    $launchPromoCode = (string) config('wolforix.launch_discount.code', '');
    $launchPlansHref = route('home').'#plans';
    $launchOfferReturnHref = route('home');
@endphp

<div data-launch-popup class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
    <button type="submit" form="launch-offer-ignore-form" class="absolute inset-0 bg-slate-950/80 backdrop-blur-md" aria-label="{{ __('site.launch_popup.close') }}"></button>
    <div class="launch-popup-card relative w-full max-w-xl overflow-hidden rounded-[2.2rem] border border-white/12 bg-[radial-gradient(circle_at_top,rgba(56,88,168,0.28),transparent_34%),linear-gradient(180deg,rgba(7,14,29,0.98),rgba(5,10,18,0.98))] px-6 py-7 shadow-[0_34px_110px_rgba(2,6,23,0.78)] sm:px-8">
        <button
            type="submit"
            form="launch-offer-ignore-form"
            class="absolute right-4 top-4 inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 transition hover:border-white/20 hover:bg-white/10 hover:text-white"
            aria-label="{{ __('site.launch_popup.close') }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 0 1 1.06 0L10 8.94l4.72-4.72a.75.75 0 1 1 1.06 1.06L11.06 10l4.72 4.72a.75.75 0 0 1-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 0 1-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </button>

        <div class="absolute inset-x-12 top-0 h-36 rounded-full bg-amber-300/25 blur-3xl"></div>
        <div class="absolute -bottom-10 left-8 h-24 w-40 rounded-full bg-sky-400/15 blur-3xl"></div>

        <div class="relative">
            <h2 id="launch-popup-title" class="max-w-lg text-3xl font-semibold leading-tight text-white sm:text-[2.8rem]">
                {{ __('site.launch_popup.title') }}
            </h2>
            <p class="mt-4 max-w-xl text-base leading-8 text-slate-300">
                {{ __('site.launch_popup.description') }}
            </p>
            <p class="mt-3 max-w-xl text-sm leading-7 text-slate-400">
                {{ __('site.launch_popup.secondary_copy') }}
            </p>

            <div class="mt-6 rounded-[1.8rem] border border-sky-300/20 bg-slate-950/45 px-5 py-4 shadow-[0_0_32px_rgba(96,165,250,0.12)] backdrop-blur-sm">
                <p class="text-center text-sm font-medium text-slate-300">{{ __('site.launch_popup.promo_label') }}</p>
                <div class="mt-3 flex justify-center">
                    <div
                        data-launch-popup-code
                        class="rounded-[1.3rem] border border-white/10 bg-white/5 px-5 py-3 text-center text-2xl font-semibold tracking-[0.02em] text-white"
                    >
                        {{ $launchPromoCode }}
                    </div>
                </div>
                <p class="mt-3 text-center text-sm leading-6 text-slate-400">{{ __('site.launch_popup.auto_apply_notice') }}</p>
            </div>

            <div class="mt-7 flex flex-col items-center gap-4">
                <button
                    type="submit"
                    form="launch-offer-apply-form"
                    class="primary-cta w-full justify-center rounded-full px-8 py-4 text-base font-semibold sm:max-w-md"
                >
                    {{ __('site.launch_popup.primary_action') }}
                </button>
                <button type="submit" form="launch-offer-ignore-form" class="rounded-full border border-white/10 px-6 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                    {{ __('site.launch_popup.secondary_action') }}
                </button>
            </div>

            <div class="mt-7 space-y-2.5">
                @foreach (trans('site.launch_popup.benefits') as $benefit)
                    <div class="flex items-center gap-3 text-sm text-slate-200">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-emerald-400/24 bg-emerald-500/10 text-emerald-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                            </svg>
                        </span>
                        <span>{{ $benefit }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<form id="launch-offer-apply-form" method="POST" action="{{ route('launch-offer.update') }}" class="hidden">
    @csrf
    <input type="hidden" name="decision" value="apply">
    <input type="hidden" name="redirect_to" value="{{ $launchPlansHref }}">
</form>

<form id="launch-offer-ignore-form" method="POST" action="{{ route('launch-offer.update') }}" class="hidden">
    @csrf
    <input type="hidden" name="decision" value="ignore">
    <input type="hidden" name="redirect_to" value="{{ $launchOfferReturnHref }}">
</form>
