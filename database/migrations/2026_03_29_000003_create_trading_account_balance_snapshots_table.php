<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trading_account_balance_snapshots')) {
            return;
        }

        Schema::create('trading_account_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_account_id')->constrained()->cascadeOnDelete();
            $table->timestamp('snapshot_at');
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('equity', 12, 2)->default(0);
            $table->decimal('profit_loss', 12, 2)->default(0);
            $table->decimal('total_profit', 12, 2)->default(0);
            $table->decimal('today_profit', 12, 2)->default(0);
            $table->decimal('daily_drawdown', 12, 2)->default(0);
            $table->decimal('max_drawdown', 12, 2)->default(0);
            $table->decimal('drawdown_percent', 5, 2)->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_account_balance_snapshots');
    }
};
