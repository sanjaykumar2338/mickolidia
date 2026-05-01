@php
    $launchPromoCode = (string) config('wolforix.launch_discount.code', '');
    $launchPlansHref = route('home').'#plans';
    $launchOfferReturnHref = route('home');
    $launchPopupVisual = asset('newfolder/mobile1.webp');
@endphp

<div
    data-launch-popup
    data-launch-popup-delay="10000"
    data-launch-popup-endpoint="{{ route('launch-offer.update') }}"
    data-launch-popup-decision="ignore"
    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-6 sm:items-center sm:p-6"
    role="dialog"
    aria-modal="true"
    aria-labelledby="launch-popup-title"
    aria-hidden="true"
    style="display: none;"
>
    <button
        type="button"
        data-launch-popup-close
        class="fixed inset-0 bg-black/[0.84] backdrop-blur-sm"
        aria-label="{{ __('site.launch_popup.close') }}"
    ></button>
    <div class="launch-popup-card relative w-full rounded-[1.65rem] border border-slate-500/20 bg-[linear-gradient(180deg,rgba(9,18,31,0.99),rgba(4,10,18,0.99))] shadow-[0_34px_110px_rgba(0,0,0,0.86)] sm:rounded-[2rem]">
        <button
            type="button"
            data-launch-popup-close
            class="absolute right-4 top-4 z-30 inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/[0.08] text-slate-300 shadow-[0_14px_34px_rgba(0,0,0,0.32)] backdrop-blur-md transition hover:border-white/20 hover:bg-white/[0.14] hover:text-white"
            aria-label="{{ __('site.launch_popup.close') }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 0 1 1.06 0L10 8.94l4.72-4.72a.75.75 0 1 1 1.06 1.06L11.06 10l4.72 4.72a.75.75 0 0 1-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 0 1-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </button>

        <div class="relative min-h-[34rem] overflow-hidden px-5 pb-8 pt-7 sm:min-h-[37.5rem] sm:px-10 sm:pb-10 sm:pt-9">
            <img
                src="{{ $launchPopupVisual }}"
                alt=""
                class="absolute inset-0 h-full w-full object-cover object-center opacity-[0.78]"
                loading="eager"
                aria-hidden="true"
            >
            <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(90deg,rgba(4,10,18,0.94)_0%,rgba(4,10,18,0.8)_33%,rgba(4,10,18,0.28)_68%,rgba(4,10,18,0.08)_100%)]"></div>
            <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(4,10,18,0.78)_0%,rgba(4,10,18,0.08)_42%,rgba(4,10,18,0.92)_100%)]"></div>
            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-1/2 bg-[radial-gradient(circle_at_center,rgba(251,191,36,0.16),transparent_55%)]"></div>

            <div class="relative z-10 flex min-h-[29rem] flex-col sm:min-h-[32rem]">
                <div class="max-w-md pr-12">
                    <h2 id="launch-popup-title" class="text-[1.85rem] font-semibold leading-tight text-white sm:text-[2.45rem]">
                        {{ __('site.launch_popup.title') }}
                    </h2>
                    <p class="mt-4 max-w-sm text-sm leading-7 text-slate-300 sm:text-base">
                        {{ __('site.launch_popup.description') }}
                    </p>
                    <p class="mt-3 max-w-sm text-sm leading-7 text-slate-300/90 sm:text-base">
                        {{ __('site.launch_popup.secondary_copy') }}
                    </p>
                </div>

                <div class="mt-auto w-full max-w-[28rem] self-center rounded-[1.55rem] border border-sky-200/20 bg-slate-950/60 px-4 py-4 shadow-[0_22px_60px_rgba(2,6,23,0.5)] backdrop-blur-md sm:px-5">
                    <p class="text-center text-sm font-medium text-slate-300">{{ __('site.launch_popup.promo_label') }}</p>
                    <div class="mt-2.5 flex justify-center">
                        <div
                            data-launch-popup-code
                            class="max-w-full rounded-[1.05rem] border border-white/10 bg-white/[0.07] px-5 py-2 text-center text-2xl font-semibold tracking-[0.02em] text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.08)] sm:text-[2rem]"
                        >
                            {{ $launchPromoCode }}
                        </div>
                    </div>
                    <p class="mx-auto mt-3 max-w-sm text-center text-sm leading-6 text-slate-300/86">{{ __('site.launch_popup.auto_apply_notice') }}</p>
                </div>
            </div>
        </div>

        <div class="px-5 pb-5 pt-5 sm:px-10 sm:pb-7">
            <div class="flex flex-col items-center gap-3">
                <button
                    type="submit"
                    form="launch-offer-apply-form"
                    class="primary-cta w-full justify-center rounded-full px-7 py-4 text-base font-extrabold shadow-[0_18px_48px_rgba(244,183,74,0.28)] sm:max-w-[38rem] sm:text-lg"
                >
                    {{ __('site.launch_popup.primary_action') }}
                </button>
                <button type="button" data-launch-popup-close class="min-w-36 rounded-full border border-white/10 bg-white/[0.02] px-8 py-3 text-sm font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.04)] transition hover:border-white/20 hover:bg-white/[0.07] sm:text-base">
                    {{ __('site.launch_popup.secondary_action') }}
                </button>
            </div>

            <div class="mx-auto mt-5 max-w-[38rem] space-y-2.5">
                @foreach (trans('site.launch_popup.benefits') as $benefit)
                    <div class="flex items-start gap-3 text-sm text-slate-200">
                        <span class="inline-flex h-6 w-6 flex-none items-center justify-center rounded-full border border-emerald-400/25 bg-emerald-500/15 text-emerald-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                            </svg>
                        </span>
                        <span class="min-w-0 leading-6 break-words">{{ $benefit }}</span>
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
