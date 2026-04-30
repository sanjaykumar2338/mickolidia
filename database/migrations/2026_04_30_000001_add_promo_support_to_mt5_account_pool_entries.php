<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mt5_account_pool_entries') && ! Schema::hasColumn('mt5_account_pool_entries', 'is_promo')) {
            Schema::table('mt5_account_pool_entries', function (Blueprint $table): void {
                $table->boolean('is_promo')->default(false)->after('allocated_at')->index();
            });
        }

        if (! Schema::hasTable('mt5_promo_codes')) {
            Schema::create('mt5_promo_codes', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->foreignId('mt5_account_pool_entry_id')->unique()->constrained('mt5_account_pool_entries')->cascadeOnDelete();
                $table->string('mt5_login')->index();
                $table->timestamp('used_at')->nullable()->index();
                $table->foreignId('used_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('used_order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('used_trading_account_id')->nullable()->constrained('trading_accounts')->nullOnDelete();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mt5_promo_codes');

        if (Schema::hasTable('mt5_account_pool_entries') && Schema::hasColumn('mt5_account_pool_entries', 'is_promo')) {
            Schema::table('mt5_account_pool_entries', function (Blueprint $table): void {
                $table->dropColumn('is_promo');
            });
        }
    }
};
