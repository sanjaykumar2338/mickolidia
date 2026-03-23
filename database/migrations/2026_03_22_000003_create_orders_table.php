<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            return;
        }

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('challenge_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('full_name');
            $table->string('street_address');
            $table->string('city');
            $table->string('postal_code', 32);
            $table->string('country', 2);
            $table->string('challenge_type');
            $table->unsignedInteger('account_size');
            $table->string('currency', 3);
            $table->string('payment_provider', 32);
            $table->decimal('base_price', 10, 2);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('final_price', 10, 2);
            $table->string('payment_status', 32)->default('pending');
            $table->string('order_status', 32)->default('created');
            $table->string('external_checkout_id')->nullable()->index();
            $table->string('external_payment_id')->nullable()->index();
            $table->string('external_customer_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
