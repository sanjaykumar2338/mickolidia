<?php

namespace App\Http\Controllers;

use App\Services\Pricing\ChallengePricingService;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function home(ChallengePricingService $pricingService): View
    {
        $challengeCatalog = $pricingService->catalog();
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
        ]);
    }

    public function about(): View
    {
        return view('public.about');
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
}
