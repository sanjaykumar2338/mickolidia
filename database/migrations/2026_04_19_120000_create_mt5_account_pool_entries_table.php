<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mt5_account_pool_entries')) {
            return;
        }

        Schema::create('mt5_account_pool_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('login');
            $table->text('password');
            $table->string('server');
            $table->unsignedInteger('account_size');
            $table->string('currency_code', 3)->nullable();
            $table->string('source_status')->nullable();
            $table->string('source_file');
            $table->string('source_batch');
            $table->string('source_pool')->default('client_pool');
            $table->date('source_created_at')->nullable();
            $table->foreignId('allocated_trading_account_id')->nullable()->constrained('trading_accounts')->nullOnDelete();
            $table->foreignId('allocated_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('allocated_at')->nullable();
            $table->boolean('is_available')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['login', 'server']);
            $table->index(['source_pool', 'is_available', 'account_size'], 'mt5_pool_entries_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mt5_account_pool_entries');
    }
};
