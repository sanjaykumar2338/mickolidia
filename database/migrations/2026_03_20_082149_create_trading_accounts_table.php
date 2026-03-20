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
        Schema::create('trading_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('challenge_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_reference')->nullable()->unique();
            $table->string('platform')->default('cTrader');
            $table->string('stage')->default('Challenge Step 1');
            $table->string('status')->default('active');
            $table->decimal('starting_balance', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('total_profit', 12, 2)->default(0);
            $table->decimal('today_profit', 12, 2)->default(0);
            $table->decimal('drawdown_percent', 5, 2)->default(0);
            $table->decimal('consistency_limit_percent', 5, 2)->default(40);
            $table->unsignedTinyInteger('minimum_trading_days')->default(3);
            $table->unsignedTinyInteger('trading_days_completed')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_accounts');
    }
};
