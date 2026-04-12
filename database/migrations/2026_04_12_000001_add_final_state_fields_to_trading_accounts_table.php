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
            'trading_blocked' => ! Schema::hasColumn('trading_accounts', 'trading_blocked'),
            'final_state_locked' => ! Schema::hasColumn('trading_accounts', 'final_state_locked'),
            'failed_email_sent_at' => ! Schema::hasColumn('trading_accounts', 'failed_email_sent_at'),
            'passed_email_sent_at' => ! Schema::hasColumn('trading_accounts', 'passed_email_sent_at'),
        ];

        if (! array_filter($missingColumns)) {
            return;
        }

        Schema::table('trading_accounts', function (Blueprint $table) use ($missingColumns) {
            if ($missingColumns['trading_blocked']) {
                $table->boolean('trading_blocked')->default(false)->after('failure_context');
            }

            if ($missingColumns['final_state_locked']) {
                $table->boolean('final_state_locked')->default(false)->after('trading_blocked');
            }

            if ($missingColumns['failed_email_sent_at']) {
                $table->timestamp('failed_email_sent_at')->nullable()->after('final_state_locked');
            }

            if ($missingColumns['passed_email_sent_at']) {
                $table->timestamp('passed_email_sent_at')->nullable()->after('failed_email_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trading_accounts')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('trading_accounts', 'passed_email_sent_at') ? 'passed_email_sent_at' : null,
            Schema::hasColumn('trading_accounts', 'failed_email_sent_at') ? 'failed_email_sent_at' : null,
            Schema::hasColumn('trading_accounts', 'final_state_locked') ? 'final_state_locked' : null,
            Schema::hasColumn('trading_accounts', 'trading_blocked') ? 'trading_blocked' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('trading_accounts', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
