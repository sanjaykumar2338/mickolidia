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
        return view('public.home', [
            'plans' => config('wolforix.challenge_plans'),
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

        $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'plan' => ['required', Rule::in($planSlugs)],
            'accept_terms' => ['accepted'],
        ], [
            'accept_terms.accepted' => __('site.checkout.validation.accept_terms'),
        ]);

        return back()->with('checkout_success', __('site.checkout.stub_notice'));
    }
}
