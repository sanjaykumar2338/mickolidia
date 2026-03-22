<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('plan_type')->nullable()->after('password');
            $table->unsignedInteger('account_size')->nullable()->after('plan_type');
            $table->decimal('payment_amount', 10, 2)->nullable()->after('account_size');
            $table->string('status')->default('active')->after('payment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'plan_type',
                'account_size',
                'payment_amount',
                'status',
            ]);
        });
    }
};
