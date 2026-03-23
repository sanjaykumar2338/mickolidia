<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $missingColumns = [
            'plan_type' => ! Schema::hasColumn('users', 'plan_type'),
            'account_size' => ! Schema::hasColumn('users', 'account_size'),
            'payment_amount' => ! Schema::hasColumn('users', 'payment_amount'),
            'status' => ! Schema::hasColumn('users', 'status'),
        ];

        if (! array_filter($missingColumns)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($missingColumns) {
            if ($missingColumns['plan_type']) {
                $table->string('plan_type')->nullable();
            }

            if ($missingColumns['account_size']) {
                $table->unsignedInteger('account_size')->nullable();
            }

            if ($missingColumns['payment_amount']) {
                $table->decimal('payment_amount', 10, 2)->nullable();
            }

            if ($missingColumns['status']) {
                $table->string('status')->default('active');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $columns = array_values(array_filter([
            'plan_type' => Schema::hasColumn('users', 'plan_type') ? 'plan_type' : null,
            'account_size' => Schema::hasColumn('users', 'account_size') ? 'account_size' : null,
            'payment_amount' => Schema::hasColumn('users', 'payment_amount') ? 'payment_amount' : null,
            'status' => Schema::hasColumn('users', 'status') ? 'status' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
