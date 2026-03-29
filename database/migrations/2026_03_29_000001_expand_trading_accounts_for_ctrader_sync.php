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
            'order_id' => ! Schema::hasColumn('trading_accounts', 'order_id'),
            'challenge_purchase_id' => ! Schema::hasColumn('trading_accounts', 'challenge_purchase_id'),
            'challenge_type' => ! Schema::hasColumn('trading_accounts', 'challenge_type'),
            'account_size' => ! Schema::hasColumn('trading_accounts', 'account_size'),
            'platform_slug' => ! Schema::hasColumn('trading_accounts', 'platform_slug'),
            'platform_account_id' => ! Schema::hasColumn('trading_accounts', 'platform_account_id'),
            'platform_login' => ! Schema::hasColumn('trading_accounts', 'platform_login'),
            'platform_environment' => ! Schema::hasColumn('trading_accounts', 'platform_environment'),
            'platform_status' => ! Schema::hasColumn('trading_accounts', 'platform_status'),
            'account_phase' => ! Schema::hasColumn('trading_accounts', 'account_phase'),
            'phase_index' => ! Schema::hasColumn('trading_accounts', 'phase_index'),
            'account_status' => ! Schema::hasColumn('trading_accounts', 'account_status'),
            'is_funded' => ! Schema::hasColumn('trading_accounts', 'is_funded'),
            'activated_at' => ! Schema::hasColumn('trading_accounts', 'activated_at'),
            'passed_at' => ! Schema::hasColumn('trading_accounts', 'passed_at'),
            'failed_at' => ! Schema::hasColumn('trading_accounts', 'failed_at'),
            'profit_target_percent' => ! Schema::hasColumn('trading_accounts', 'profit_target_percent'),
            'profit_target_amount' => ! Schema::hasColumn('trading_accounts', 'profit_target_amount'),
            'profit_target_progress_percent' => ! Schema::hasColumn('trading_accounts', 'profit_target_progress_percent'),
            'daily_drawdown_limit_percent' => ! Schema::hasColumn('trading_accounts', 'daily_drawdown_limit_percent'),
            'daily_drawdown_limit_amount' => ! Schema::hasColumn('trading_accounts', 'daily_drawdown_limit_amount'),
            'max_drawdown_limit_percent' => ! Schema::hasColumn('trading_accounts', 'max_drawdown_limit_percent'),
            'max_drawdown_limit_amount' => ! Schema::hasColumn('trading_accounts', 'max_drawdown_limit_amount'),
            'profit_split' => ! Schema::hasColumn('trading_accounts', 'profit_split'),
            'payout_eligible_at' => ! Schema::hasColumn('trading_accounts', 'payout_eligible_at'),
            'first_payout_eligible_at' => ! Schema::hasColumn('trading_accounts', 'first_payout_eligible_at'),
            'payout_cycle_started_at' => ! Schema::hasColumn('trading_accounts', 'payout_cycle_started_at'),
            'last_balance_change_at' => ! Schema::hasColumn('trading_accounts', 'last_balance_change_at'),
            'sync_status' => ! Schema::hasColumn('trading_accounts', 'sync_status'),
            'last_synced_at' => ! Schema::hasColumn('trading_accounts', 'last_synced_at'),
            'last_sync_started_at' => ! Schema::hasColumn('trading_accounts', 'last_sync_started_at'),
            'last_sync_completed_at' => ! Schema::hasColumn('trading_accounts', 'last_sync_completed_at'),
            'sync_error' => ! Schema::hasColumn('trading_accounts', 'sync_error'),
            'sync_error_at' => ! Schema::hasColumn('trading_accounts', 'sync_error_at'),
            'rule_state' => ! Schema::hasColumn('trading_accounts', 'rule_state'),
        ];

        if (! array_filter($missingColumns)) {
            return;
        }

        Schema::table('trading_accounts', function (Blueprint $table) use ($missingColumns) {
            if ($missingColumns['order_id']) {
                $table->foreignId('order_id')->nullable()->after('challenge_plan_id')->constrained()->nullOnDelete();
            }

            if ($missingColumns['challenge_purchase_id']) {
                $table->foreignId('challenge_purchase_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            }

            if ($missingColumns['challenge_type']) {
                $table->string('challenge_type')->nullable()->after('challenge_purchase_id');
            }

            if ($missingColumns['account_size']) {
                $table->unsignedInteger('account_size')->nullable()->after('challenge_type');
            }

            if ($missingColumns['platform_slug']) {
                $table->string('platform_slug')->default('ctrader')->after('platform');
            }

            if ($missingColumns['platform_account_id']) {
                $table->string('platform_account_id')->nullable()->after('platform_slug')->index();
            }

            if ($missingColumns['platform_login']) {
                $table->string('platform_login')->nullable()->after('platform_account_id')->index();
            }

            if ($missingColumns['platform_environment']) {
                $table->string('platform_environment')->default('demo')->after('platform_login');
            }

            if ($missingColumns['platform_status']) {
                $table->string('platform_status')->nullable()->after('platform_environment');
            }

            if ($missingColumns['account_phase']) {
                $table->string('account_phase')->default('challenge')->after('account_type');
            }

            if ($missingColumns['phase_index']) {
                $table->unsignedTinyInteger('phase_index')->default(1)->after('account_phase');
            }

            if ($missingColumns['account_status']) {
                $table->string('account_status')->default('pending_activation')->after('status');
            }

            if ($missingColumns['is_funded']) {
                $table->boolean('is_funded')->default(false)->after('account_status');
            }

            if ($missingColumns['activated_at']) {
                $table->timestamp('activated_at')->nullable()->after('is_funded');
            }

            if ($missingColumns['passed_at']) {
                $table->timestamp('passed_at')->nullable()->after('activated_at');
            }

            if ($missingColumns['failed_at']) {
                $table->timestamp('failed_at')->nullable()->after('passed_at');
            }

            if ($missingColumns['profit_target_percent']) {
                $table->decimal('profit_target_percent', 5, 2)->default(0)->after('drawdown_percent');
            }

            if ($missingColumns['profit_target_amount']) {
                $table->decimal('profit_target_amount', 12, 2)->default(0)->after('profit_target_percent');
            }

            if ($missingColumns['profit_target_progress_percent']) {
                $table->decimal('profit_target_progress_percent', 6, 2)->default(0)->after('profit_target_amount');
            }

            if ($missingColumns['daily_drawdown_limit_percent']) {
                $table->decimal('daily_drawdown_limit_percent', 5, 2)->default(0)->after('profit_target_progress_percent');
            }

            if ($missingColumns['daily_drawdown_limit_amount']) {
                $table->decimal('daily_drawdown_limit_amount', 12, 2)->default(0)->after('daily_drawdown_limit_percent');
            }

            if ($missingColumns['max_drawdown_limit_percent']) {
                $table->decimal('max_drawdown_limit_percent', 5, 2)->default(0)->after('daily_drawdown_limit_amount');
            }

            if ($missingColumns['max_drawdown_limit_amount']) {
                $table->decimal('max_drawdown_limit_amount', 12, 2)->default(0)->after('max_drawdown_limit_percent');
            }

            if ($missingColumns['profit_split']) {
                $table->decimal('profit_split', 5, 2)->default(0)->after('max_drawdown_limit_amount');
            }

            if ($missingColumns['payout_eligible_at']) {
                $table->timestamp('payout_eligible_at')->nullable()->after('profit_split');
            }

            if ($missingColumns['first_payout_eligible_at']) {
                $table->timestamp('first_payout_eligible_at')->nullable()->after('payout_eligible_at');
            }

            if ($missingColumns['payout_cycle_started_at']) {
                $table->timestamp('payout_cycle_started_at')->nullable()->after('first_payout_eligible_at');
            }

            if ($missingColumns['last_balance_change_at']) {
                $table->timestamp('last_balance_change_at')->nullable()->after('payout_cycle_started_at');
            }

            if ($missingColumns['sync_status']) {
                $table->string('sync_status')->default('pending')->after('synced_at');
            }

            if ($missingColumns['last_synced_at']) {
                $table->timestamp('last_synced_at')->nullable()->after('sync_status');
            }

            if ($missingColumns['last_sync_started_at']) {
                $table->timestamp('last_sync_started_at')->nullable()->after('last_synced_at');
            }

            if ($missingColumns['last_sync_completed_at']) {
                $table->timestamp('last_sync_completed_at')->nullable()->after('last_sync_started_at');
            }

            if ($missingColumns['sync_error']) {
                $table->text('sync_error')->nullable()->after('last_sync_completed_at');
            }

            if ($missingColumns['sync_error_at']) {
                $table->timestamp('sync_error_at')->nullable()->after('sync_error');
            }

            if ($missingColumns['rule_state']) {
                $table->json('rule_state')->nullable()->after('sync_error_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trading_accounts')) {
            return;
        }

        Schema::table('trading_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('trading_accounts', 'challenge_purchase_id')) {
                $table->dropConstrainedForeignId('challenge_purchase_id');
            }

            if (Schema::hasColumn('trading_accounts', 'order_id')) {
                $table->dropConstrainedForeignId('order_id');
            }
        });

        $columns = array_values(array_filter([
            Schema::hasColumn('trading_accounts', 'challenge_type') ? 'challenge_type' : null,
            Schema::hasColumn('trading_accounts', 'account_size') ? 'account_size' : null,
            Schema::hasColumn('trading_accounts', 'platform_slug') ? 'platform_slug' : null,
            Schema::hasColumn('trading_accounts', 'platform_account_id') ? 'platform_account_id' : null,
            Schema::hasColumn('trading_accounts', 'platform_login') ? 'platform_login' : null,
            Schema::hasColumn('trading_accounts', 'platform_environment') ? 'platform_environment' : null,
            Schema::hasColumn('trading_accounts', 'platform_status') ? 'platform_status' : null,
            Schema::hasColumn('trading_accounts', 'account_phase') ? 'account_phase' : null,
            Schema::hasColumn('trading_accounts', 'phase_index') ? 'phase_index' : null,
            Schema::hasColumn('trading_accounts', 'account_status') ? 'account_status' : null,
            Schema::hasColumn('trading_accounts', 'is_funded') ? 'is_funded' : null,
            Schema::hasColumn('trading_accounts', 'activated_at') ? 'activated_at' : null,
            Schema::hasColumn('trading_accounts', 'passed_at') ? 'passed_at' : null,
            Schema::hasColumn('trading_accounts', 'failed_at') ? 'failed_at' : null,
            Schema::hasColumn('trading_accounts', 'profit_target_percent') ? 'profit_target_percent' : null,
            Schema::hasColumn('trading_accounts', 'profit_target_amount') ? 'profit_target_amount' : null,
            Schema::hasColumn('trading_accounts', 'profit_target_progress_percent') ? 'profit_target_progress_percent' : null,
            Schema::hasColumn('trading_accounts', 'daily_drawdown_limit_percent') ? 'daily_drawdown_limit_percent' : null,
            Schema::hasColumn('trading_accounts', 'daily_drawdown_limit_amount') ? 'daily_drawdown_limit_amount' : null,
            Schema::hasColumn('trading_accounts', 'max_drawdown_limit_percent') ? 'max_drawdown_limit_percent' : null,
            Schema::hasColumn('trading_accounts', 'max_drawdown_limit_amount') ? 'max_drawdown_limit_amount' : null,
            Schema::hasColumn('trading_accounts', 'profit_split') ? 'profit_split' : null,
            Schema::hasColumn('trading_accounts', 'payout_eligible_at') ? 'payout_eligible_at' : null,
            Schema::hasColumn('trading_accounts', 'first_payout_eligible_at') ? 'first_payout_eligible_at' : null,
            Schema::hasColumn('trading_accounts', 'payout_cycle_started_at') ? 'payout_cycle_started_at' : null,
            Schema::hasColumn('trading_accounts', 'last_balance_change_at') ? 'last_balance_change_at' : null,
            Schema::hasColumn('trading_accounts', 'sync_status') ? 'sync_status' : null,
            Schema::hasColumn('trading_accounts', 'last_synced_at') ? 'last_synced_at' : null,
            Schema::hasColumn('trading_accounts', 'last_sync_started_at') ? 'last_sync_started_at' : null,
            Schema::hasColumn('trading_accounts', 'last_sync_completed_at') ? 'last_sync_completed_at' : null,
            Schema::hasColumn('trading_accounts', 'sync_error') ? 'sync_error' : null,
            Schema::hasColumn('trading_accounts', 'sync_error_at') ? 'sync_error_at' : null,
            Schema::hasColumn('trading_accounts', 'rule_state') ? 'rule_state' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('trading_accounts', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
