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

    public function test_home_page_contains_the_refined_challenge_selector_and_fixed_disclaimer(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('1-Step Challenge')
            ->assertSee('2-Step Challenge')
            ->assertSee('5K')
            ->assertSee('100K')
            ->assertSee('Payout Policy')
            ->assertSee('Dismiss notice')
            ->assertSee('Street address')
            ->assertSee('Country');
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
            'plan' => 'two-step-50000',
        ]);

        $response
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors([
                'street_address',
                'city',
                'postal_code',
                'country',
            ])
            ->assertSessionHasErrors('accept_terms');
    }

    public function test_checkout_stub_accepts_a_valid_payload(): void
    {
        $response = $this->from(route('home'))->post(route('challenge.checkout.store'), [
            'full_name' => 'Test Trader',
            'email' => 'trader@example.com',
            'street_address' => '1 Market Street',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
            'plan' => 'two-step-50000',
            'accept_terms' => '1',
        ]);

        $response
            ->assertRedirect(route('home'))
            ->assertSessionHas('checkout_success');
    }

    public function test_payout_policy_contains_the_updated_cycle_wording(): void
    {
        $this->get(route('payout-policy'))
            ->assertOk()
            ->assertSee('Payouts are processed in bi-weekly cycles with a maximum limit per cycle.');
    }
}
