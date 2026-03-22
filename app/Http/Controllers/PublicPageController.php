<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function home(): View
    {
        $challengeCatalog = config('wolforix.challenge_catalog', []);
        $defaultChallengeType = array_key_first($challengeCatalog);
        $defaultChallengeSize = $defaultChallengeType !== null
            ? (string) array_key_first($challengeCatalog[$defaultChallengeType]['plans'])
            : null;

        return view('public.home', [
            'plans' => config('wolforix.challenge_plans'),
            'challengeCatalog' => $challengeCatalog,
            'challengeSizes' => config('wolforix.challenge_sizes', []),
            'currencies' => config('wolforix.currencies', []),
            'defaultCurrency' => config('wolforix.default_currency', 'USD'),
            'defaultChallengeType' => $defaultChallengeType,
            'defaultChallengeSize' => $defaultChallengeSize,
            'checkoutCountries' => config('wolforix.checkout_countries', []),
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

    public function storeChallengeCheckout(Request $request): RedirectResponse
    {
        $planSlugs = collect(config('wolforix.challenge_plans'))
            ->pluck('slug')
            ->all();
        $countryCodes = array_keys(config('wolforix.checkout_countries', []));

        $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'street_address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:32'],
            'country' => ['required', Rule::in($countryCodes)],
            'plan' => ['required', Rule::in($planSlugs)],
            'accept_terms' => ['accepted'],
        ], [
            'accept_terms.accepted' => __('site.checkout.validation.accept_terms'),
        ]);

        return back()->with('checkout_success', __('site.checkout.stub_notice'));
    }
}
