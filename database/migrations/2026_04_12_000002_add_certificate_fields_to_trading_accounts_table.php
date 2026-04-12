<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trading_accounts')) {
            return;
        }

        $missingColumns = [
            'certificate_path' => ! Schema::hasColumn('trading_accounts', 'certificate_path'),
            'certificate_generated_at' => ! Schema::hasColumn('trading_accounts', 'certificate_generated_at'),
        ];

        if (! in_array(true, $missingColumns, true)) {
            return;
        }

        Schema::table('trading_accounts', function (Blueprint $table) use ($missingColumns): void {
            if ($missingColumns['certificate_path']) {
                $table->string('certificate_path')->nullable()->after('passed_email_sent_at');
            }

            if ($missingColumns['certificate_generated_at']) {
                $table->timestamp('certificate_generated_at')->nullable()->after('certificate_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trading_accounts')) {
            return;
        }

        $columns = array_filter([
            Schema::hasColumn('trading_accounts', 'certificate_generated_at') ? 'certificate_generated_at' : null,
            Schema::hasColumn('trading_accounts', 'certificate_path') ? 'certificate_path' : null,
        ]);

        if ($columns === []) {
            return;
        }

        Schema::table('trading_accounts', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
