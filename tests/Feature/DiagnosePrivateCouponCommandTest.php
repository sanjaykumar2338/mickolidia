<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiagnosePrivateCouponCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('wolforix.launch_discount', [
            'enabled' => true,
            'type' => 'percentage',
            'percent' => 20,
            'code' => 'Wolforix2026',
            'campaign' => 'test_launch_discount',
            'ends_at' => '2026-05-31 23:59:59',
            'single_use_per_customer' => false,
            'badge' => '20% OFF - Launch Access Ending Soon',
            'urgency_text' => 'Launch Discount - Limited Time Only',
        ]);
        config()->set('wolforix.private_coupon', [
            'enabled' => true,
            'type' => 'percentage',
            'percent' => 50,
            'code' => 'WOLF50HQ',
            'campaign' => 'test_private_manual_coupon',
            'single_use' => true,
            'badge' => 'Code applied',
        ]);
    }

    public function test_diagnose_private_coupon_reports_live_schema_and_valid_private_coupon_read_only(): void
    {
        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|alter|drop|create|truncate|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $exitCode = Artisan::call('wolforix:diagnose-private-coupon', ['code' => 'WOLF50HQ']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Read-only private coupon diagnosis', $output);
        $this->assertStringContainsString('No writes are performed by this command.', $output);
        $this->assertStringContainsString('PRIVATE_PROMO_ENABLED', $output);
        $this->assertStringContainsString('PRIVATE_PROMO_CODE', $output);
        $this->assertStringContainsString('PRIVATE_PROMO_PERCENT', $output);
        $this->assertStringContainsString('LAUNCH_PROMO_CODE', $output);
        $this->assertStringContainsString('CheckoutController stores promo discounts on Order.metadata.launch_promo.', $output);
        $this->assertStringContainsString('Payment attempts use payment_attempts, not a payments table.', $output);
        $this->assertStringContainsString('No promo_redemptions table is used.', $output);
        $this->assertStringContainsString('Order', $output);
        $this->assertStringContainsString('orders', $output);
        $this->assertStringContainsString('payment_attempts', $output);
        $this->assertStringContainsString('challenge_purchases', $output);
        $this->assertStringContainsString('metadata', $output);
        $this->assertStringContainsString('payment_status', $output);
        $this->assertStringContainsString('Checkout should accept this as a private/manual-only coupon.', $output);
        $this->assertStringContainsString('Discount: 50.00%', $output);
        $this->assertStringNotContainsString('Checkout would return: Invalid or expired promo code.', $output);
        $this->assertSame([], $writes);
    }

    public function test_diagnose_private_coupon_finds_used_coupon_in_orders_metadata_read_only(): void
    {
        $user = User::factory()->create(['email' => 'used-coupon@example.com']);
        $order = Order::query()->create([
            'user_id' => $user->id,
            'email' => 'used-coupon@example.com',
            'full_name' => 'Used Coupon Trader',
            'street_address' => '1 Coupon Street',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'currency' => 'USD',
            'payment_provider' => 'stripe',
            'base_price' => 79,
            'discount_percent' => 50,
            'discount_amount' => 39,
            'final_price' => 40,
            'payment_status' => Order::PAYMENT_PAID,
            'order_status' => Order::STATUS_COMPLETED,
            'metadata' => [
                'launch_promo' => [
                    'code' => 'WOLF50HQ',
                    'applied' => true,
                    'kind' => 'private_coupon',
                    'redeemed' => true,
                    'redeemed_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|alter|drop|create|truncate|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $exitCode = Artisan::call('wolforix:diagnose-private-coupon', ['code' => 'WOLF50HQ']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Search results for WOLF50HQ in safe text/json columns', $output);
        $this->assertStringContainsString('orders', $output);
        $this->assertStringContainsString('metadata', $output);
        $this->assertStringContainsString($order->order_number, $output);
        $this->assertStringContainsString('Checkout would return: This promo code has already been used.', $output);
        $this->assertSame([], $writes);
    }

    public function test_diagnose_private_coupon_explains_invalid_config(): void
    {
        config()->set('wolforix.private_coupon.enabled', false);

        $exitCode = Artisan::call('wolforix:diagnose-private-coupon', ['code' => 'WOLF50HQ']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Checkout would return: Invalid or expired promo code.', $output);
        $this->assertStringContainsString('PRIVATE_PROMO_ENABLED resolves to false in config.', $output);
    }
}
