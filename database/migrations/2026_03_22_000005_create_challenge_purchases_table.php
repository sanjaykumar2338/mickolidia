<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('challenge_type');
            $table->unsignedInteger('account_size');
            $table->string('currency', 3);
            $table->string('account_status', 32)->default('pending_activation');
            $table->string('funded_status', 32)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_purchases');
    }
};
