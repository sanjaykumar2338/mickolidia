<?php

namespace App\Http\Controllers;

use App\Services\Pricing\ChallengePricingService;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function home(
        Request $request,
        ChallengePricingService $pricingService,
        PaymentManager $paymentManager,
    ): View
    {
        $this->syncLaunchOfferSession($request, $pricingService);

        $launchDiscountApplied = $pricingService->launchDiscountApplied($request);
        $challengeCatalog = $pricingService->catalog(null, $launchDiscountApplied);
        $defaultChallengeType = $pricingService->defaultChallengeType();
        $defaultChallengeSize = $pricingService->defaultChallengeSize($defaultChallengeType);

        return view('public.home', [
            'challengeCatalog' => $challengeCatalog,
            'challengeSizes' => $pricingService->sizes(),
            'currencies' => config('wolforix.currencies', []),
            'defaultCurrency' => config('wolforix.default_currency', 'USD'),
            'defaultChallengeType' => $defaultChallengeType,
            'defaultChallengeSize' => $defaultChallengeSize,
            'checkoutCountries' => config('wolforix.checkout_countries', []),
            'launchPromoCode' => $pricingService->launchPromoCodeForRequest($request),
            'paymentProviders' => $paymentManager->providers(),
        ]);
    }

    public function updateLaunchOffer(Request $request, ChallengePricingService $pricingService): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:apply,ignore'],
            'redirect_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $decision = (string) $validated['decision'];
        $promoCode = trim((string) config('wolforix.launch_discount.code', ''));

        if ($decision === 'apply' && $promoCode !== '') {
            $request->session()->put('launch_offer', [
                'decision' => 'apply',
                'applied' => true,
                'promo_code' => $promoCode,
            ]);
        } else {
            $request->session()->put('launch_offer', [
                'decision' => 'ignore',
                'applied' => false,
                'promo_code' => null,
            ]);
        }

        return redirect()->to($this->sanitizeInternalRedirect((string) ($validated['redirect_to'] ?? route('home'))));
    }

    public function about(): View
    {
        return view('public.about');
    }

    public function contact(): View
    {
        return view('public.contact', [
            'faqSections' => trans('site.faq.sections'),
            'supportEmail' => config('wolforix.support.email'),
        ]);
    }

    public function faq(): View
    {
        return view('public.faq', [
            'faqSections' => trans('site.faq.sections'),
        ]);
    }

    public function legal(string $slug): View
    {
        $page = config("wolforix.legal_pages.{$slug}");

        abort_unless(is_array($page), 404);

        return view('public.legal', [
            'page' => trans('site.legal.pages.'.$page['content_key']),
            'pageSlug' => $slug,
        ]);
    }

    private function syncLaunchOfferSession(Request $request, ChallengePricingService $pricingService): void
    {
        $promoCode = $pricingService->normalizeLaunchPromoCode($request->query('promo_code'));

        if ($promoCode === null) {
            return;
        }

        $request->session()->put('launch_offer', [
            'decision' => 'apply',
            'applied' => true,
            'promo_code' => $promoCode,
        ]);
    }

    private function sanitizeInternalRedirect(string $target): string
    {
        $target = trim($target);

        if ($target === '') {
            return route('home');
        }

        if (Str::startsWith($target, '/')) {
            return $target;
        }

        $targetHost = parse_url($target, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url', route('home')), PHP_URL_HOST);

        return $targetHost !== null && $appHost !== null && strcasecmp($targetHost, $appHost) === 0
            ? $target
            : route('home');
    }
}
