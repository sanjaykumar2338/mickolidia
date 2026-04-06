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
            'challenge_status' => ! Schema::hasColumn('trading_accounts', 'challenge_status'),
            'highest_equity_today' => ! Schema::hasColumn('trading_accounts', 'highest_equity_today'),
            'daily_loss_used' => ! Schema::hasColumn('trading_accounts', 'daily_loss_used'),
            'max_drawdown_used' => ! Schema::hasColumn('trading_accounts', 'max_drawdown_used'),
            'phase_starting_balance' => ! Schema::hasColumn('trading_accounts', 'phase_starting_balance'),
            'phase_reference_balance' => ! Schema::hasColumn('trading_accounts', 'phase_reference_balance'),
            'phase_started_at' => ! Schema::hasColumn('trading_accounts', 'phase_started_at'),
            'failure_reason' => ! Schema::hasColumn('trading_accounts', 'failure_reason'),
            'failure_context' => ! Schema::hasColumn('trading_accounts', 'failure_context'),
            'sync_source' => ! Schema::hasColumn('trading_accounts', 'sync_source'),
            'server_day' => ! Schema::hasColumn('trading_accounts', 'server_day'),
            'last_evaluated_at' => ! Schema::hasColumn('trading_accounts', 'last_evaluated_at'),
        ];

        if (! array_filter($missingColumns)) {
            return;
        }

        Schema::table('trading_accounts', function (Blueprint $table) use ($missingColumns) {
            if ($missingColumns['challenge_status']) {
                $table->string('challenge_status')->default('pending_activation')->after('account_status');
            }

            if ($missingColumns['highest_equity_today']) {
                $table->decimal('highest_equity_today', 12, 2)->default(0)->after('equity');
            }

            if ($missingColumns['daily_loss_used']) {
                $table->decimal('daily_loss_used', 12, 2)->default(0)->after('daily_drawdown');
            }

            if ($missingColumns['max_drawdown_used']) {
                $table->decimal('max_drawdown_used', 12, 2)->default(0)->after('max_drawdown');
            }

            if ($missingColumns['phase_starting_balance']) {
                $table->decimal('phase_starting_balance', 12, 2)->default(0)->after('starting_balance');
            }

            if ($missingColumns['phase_reference_balance']) {
                $table->decimal('phase_reference_balance', 12, 2)->default(0)->after('phase_starting_balance');
            }

            if ($missingColumns['phase_started_at']) {
                $table->timestamp('phase_started_at')->nullable()->after('activated_at');
            }

            if ($missingColumns['failure_reason']) {
                $table->string('failure_reason')->nullable()->after('failed_at');
            }

            if ($missingColumns['failure_context']) {
                $table->json('failure_context')->nullable()->after('failure_reason');
            }

            if ($missingColumns['sync_source']) {
                $table->string('sync_source', 64)->nullable()->after('sync_status');
            }

            if ($missingColumns['server_day']) {
                $table->date('server_day')->nullable()->after('sync_source');
            }

            if ($missingColumns['last_evaluated_at']) {
                $table->timestamp('last_evaluated_at')->nullable()->after('server_day');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trading_accounts')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('trading_accounts', 'challenge_status') ? 'challenge_status' : null,
            Schema::hasColumn('trading_accounts', 'highest_equity_today') ? 'highest_equity_today' : null,
            Schema::hasColumn('trading_accounts', 'daily_loss_used') ? 'daily_loss_used' : null,
            Schema::hasColumn('trading_accounts', 'max_drawdown_used') ? 'max_drawdown_used' : null,
            Schema::hasColumn('trading_accounts', 'phase_starting_balance') ? 'phase_starting_balance' : null,
            Schema::hasColumn('trading_accounts', 'phase_reference_balance') ? 'phase_reference_balance' : null,
            Schema::hasColumn('trading_accounts', 'phase_started_at') ? 'phase_started_at' : null,
            Schema::hasColumn('trading_accounts', 'failure_reason') ? 'failure_reason' : null,
            Schema::hasColumn('trading_accounts', 'failure_context') ? 'failure_context' : null,
            Schema::hasColumn('trading_accounts', 'sync_source') ? 'sync_source' : null,
            Schema::hasColumn('trading_accounts', 'server_day') ? 'server_day' : null,
            Schema::hasColumn('trading_accounts', 'last_evaluated_at') ? 'last_evaluated_at' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('trading_accounts', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
