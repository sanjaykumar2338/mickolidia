<div data-launch-popup class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6">
    <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-md"></div>
    <div class="launch-popup-card relative w-full max-w-lg overflow-hidden rounded-[2rem] border border-amber-300/35 bg-slate-950 px-6 py-7 shadow-[0_30px_100px_rgba(2,6,23,0.72)] sm:px-8">
        <button
            type="button"
            data-launch-popup-close
            class="absolute right-4 top-4 inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 transition hover:border-white/20 hover:bg-white/10 hover:text-white"
            aria-label="{{ __('site.launch_popup.close') }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 0 1 1.06 0L10 8.94l4.72-4.72a.75.75 0 1 1 1.06 1.06L11.06 10l4.72 4.72a.75.75 0 0 1-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 0 1-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </button>

        <div class="absolute inset-x-8 top-0 h-32 rounded-full bg-amber-300/20 blur-3xl"></div>

        <div class="relative">
            <span class="gold-pill inline-flex rounded-full px-4 py-2 text-xs font-semibold">
                {{ __('site.home.challenge_selector.discount_badge') }}
            </span>
            <h2 id="launch-popup-title" class="mt-6 max-w-md text-3xl font-semibold leading-tight text-white sm:text-4xl">
                {{ __('site.launch_popup.title') }}
            </h2>
            <p class="mt-4 max-w-xl text-base leading-8 text-slate-300">
                {{ __('site.launch_popup.description') }}
            </p>

            <div class="mt-8 flex flex-wrap gap-4">
                <a href="#plans" data-launch-popup-close class="primary-cta rounded-full px-8 py-4 text-base font-semibold">
                    {{ __('site.launch_popup.primary_action') }}
                </a>
                <button type="button" data-launch-popup-close class="rounded-full border border-white/10 px-5 py-4 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/6">
                    {{ __('site.launch_popup.secondary_action') }}
                </button>
            </div>
        </div>
    </div>
</div>
