<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trading_account_status_histories')) {
            return;
        }

        Schema::create('trading_account_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_account_id')->constrained()->cascadeOnDelete();
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->unsignedTinyInteger('previous_phase_index')->nullable();
            $table->unsignedTinyInteger('new_phase_index')->nullable();
            $table->string('source', 32)->default('system');
            $table->json('context')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_account_status_histories');
    }
};
