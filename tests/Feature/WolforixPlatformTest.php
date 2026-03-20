<?php

namespace Tests\Feature;

use Tests\TestCase;

class WolforixPlatformTest extends TestCase
{
    public function test_public_pages_render_successfully(): void
    {
        foreach ([
            route('home'),
            route('faq'),
            route('terms'),
            route('risk-disclosure'),
            route('payout-policy'),
            route('refund-policy'),
            route('privacy-policy'),
            route('aml-kyc'),
            route('company-info'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_dashboard_foundation_pages_render_successfully(): void
    {
        foreach ([
            route('dashboard'),
            route('dashboard.accounts'),
            route('dashboard.payouts'),
            route('dashboard.settings'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_locale_switch_updates_session_and_redirects_back(): void
    {
        $response = $this->from(route('home'))->post(route('locale.update', 'de'), [
            'redirect' => route('home'),
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('locale', 'de');
    }

    public function test_checkout_requires_the_mandatory_agreement(): void
    {
        $response = $this->from(route('home'))->post(route('challenge.checkout.store'), [
            'full_name' => 'Test Trader',
            'email' => 'trader@example.com',
            'plan' => 'wolf-50000',
        ]);

        $response
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('accept_terms');
    }

    public function test_checkout_stub_accepts_a_valid_payload(): void
    {
        $response = $this->from(route('home'))->post(route('challenge.checkout.store'), [
            'full_name' => 'Test Trader',
            'email' => 'trader@example.com',
            'plan' => 'wolf-50000',
            'accept_terms' => '1',
        ]);

        $response
            ->assertRedirect(route('home'))
            ->assertSessionHas('checkout_success');
    }
}
