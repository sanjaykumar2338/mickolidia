<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trading_account_days')) {
            return;
        }

        Schema::create('trading_account_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('phase_index')->default(1);
            $table->date('trading_date');
            $table->unsignedInteger('activity_count')->default(0);
            $table->decimal('volume', 16, 2)->default(0);
            $table->timestamp('first_activity_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->string('source', 64)->nullable();
            $table->timestamps();

            $table->unique(['trading_account_id', 'phase_index', 'trading_date'], 'trading_account_days_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_account_days');
    }
};
