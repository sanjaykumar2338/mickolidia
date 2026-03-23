<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_attempts')) {
            return;
        }

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_session_id')->nullable()->index();
            $table->string('provider_payment_id')->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('status', 32)->default('pending');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
