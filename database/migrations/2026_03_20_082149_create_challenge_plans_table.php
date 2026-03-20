<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('challenge_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedInteger('account_size');
            $table->string('currency', 3)->default('EUR');
            $table->decimal('entry_fee', 10, 2);
            $table->decimal('profit_target', 5, 2);
            $table->decimal('daily_loss_limit', 5, 2);
            $table->decimal('max_loss_limit', 5, 2);
            $table->unsignedTinyInteger('steps')->default(2);
            $table->decimal('profit_share', 5, 2);
            $table->unsignedSmallInteger('first_payout_days')->default(14);
            $table->unsignedTinyInteger('minimum_trading_days')->default(3);
            $table->unsignedSmallInteger('payout_cycle_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_plans');
    }
};
