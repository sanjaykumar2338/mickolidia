<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trading_account_sync_logs')) {
            return;
        }

        Schema::create('trading_account_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 32)->default('ctrader');
            $table->string('status', 32)->default('pending');
            $table->string('message')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_account_sync_logs');
    }
};
