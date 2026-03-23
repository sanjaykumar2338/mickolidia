<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_profiles')) {
            return;
        }

        $missingColumns = [
            'street_address' => ! Schema::hasColumn('user_profiles', 'street_address'),
            'postal_code' => ! Schema::hasColumn('user_profiles', 'postal_code'),
        ];

        if (! array_filter($missingColumns)) {
            return;
        }

        Schema::table('user_profiles', function (Blueprint $table) use ($missingColumns) {
            if ($missingColumns['street_address']) {
                $table->string('street_address')->nullable();
            }

            if ($missingColumns['postal_code']) {
                $table->string('postal_code', 32)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_profiles')) {
            return;
        }

        $columns = array_values(array_filter([
            'street_address' => Schema::hasColumn('user_profiles', 'street_address') ? 'street_address' : null,
            'postal_code' => Schema::hasColumn('user_profiles', 'postal_code') ? 'postal_code' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('user_profiles', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
