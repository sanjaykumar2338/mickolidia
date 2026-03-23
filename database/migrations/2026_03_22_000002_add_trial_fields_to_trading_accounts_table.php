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
            'account_type' => ! Schema::hasColumn('trading_accounts', 'account_type'),
            'is_trial' => ! Schema::hasColumn('trading_accounts', 'is_trial'),
            'equity' => ! Schema::hasColumn('trading_accounts', 'equity'),
            'daily_drawdown' => ! Schema::hasColumn('trading_accounts', 'daily_drawdown'),
            'max_drawdown' => ! Schema::hasColumn('trading_accounts', 'max_drawdown'),
            'profit_loss' => ! Schema::hasColumn('trading_accounts', 'profit_loss'),
            'allowed_symbols' => ! Schema::hasColumn('trading_accounts', 'allowed_symbols'),
            'trial_status' => ! Schema::hasColumn('trading_accounts', 'trial_status'),
            'trial_started_at' => ! Schema::hasColumn('trading_accounts', 'trial_started_at'),
            'last_activity_at' => ! Schema::hasColumn('trading_accounts', 'last_activity_at'),
            'ended_at' => ! Schema::hasColumn('trading_accounts', 'ended_at'),
            'milestone_popup_3_shown' => ! Schema::hasColumn('trading_accounts', 'milestone_popup_3_shown'),
            'milestone_popup_5_shown' => ! Schema::hasColumn('trading_accounts', 'milestone_popup_5_shown'),
            'encouragement_email_sent_at' => ! Schema::hasColumn('trading_accounts', 'encouragement_email_sent_at'),
        ];

        if (! array_filter($missingColumns)) {
            return;
        }

        Schema::table('trading_accounts', function (Blueprint $table) use ($missingColumns) {
            if ($missingColumns['account_type']) {
                $table->string('account_type')->default('challenge');
            }

            if ($missingColumns['is_trial']) {
                $table->boolean('is_trial')->default(false);
            }

            if ($missingColumns['equity']) {
                $table->decimal('equity', 12, 2)->default(0);
            }

            if ($missingColumns['daily_drawdown']) {
                $table->decimal('daily_drawdown', 12, 2)->default(0);
            }

            if ($missingColumns['max_drawdown']) {
                $table->decimal('max_drawdown', 12, 2)->default(0);
            }

            if ($missingColumns['profit_loss']) {
                $table->decimal('profit_loss', 12, 2)->default(0);
            }

            if ($missingColumns['allowed_symbols']) {
                $table->json('allowed_symbols')->nullable();
            }

            if ($missingColumns['trial_status']) {
                $table->string('trial_status')->nullable();
            }

            if ($missingColumns['trial_started_at']) {
                $table->timestamp('trial_started_at')->nullable();
            }

            if ($missingColumns['last_activity_at']) {
                $table->timestamp('last_activity_at')->nullable();
            }

            if ($missingColumns['ended_at']) {
                $table->timestamp('ended_at')->nullable();
            }

            if ($missingColumns['milestone_popup_3_shown']) {
                $table->boolean('milestone_popup_3_shown')->default(false);
            }

            if ($missingColumns['milestone_popup_5_shown']) {
                $table->boolean('milestone_popup_5_shown')->default(false);
            }

            if ($missingColumns['encouragement_email_sent_at']) {
                $table->timestamp('encouragement_email_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trading_accounts')) {
            return;
        }

        $columns = array_values(array_filter([
            'account_type' => Schema::hasColumn('trading_accounts', 'account_type') ? 'account_type' : null,
            'is_trial' => Schema::hasColumn('trading_accounts', 'is_trial') ? 'is_trial' : null,
            'equity' => Schema::hasColumn('trading_accounts', 'equity') ? 'equity' : null,
            'daily_drawdown' => Schema::hasColumn('trading_accounts', 'daily_drawdown') ? 'daily_drawdown' : null,
            'max_drawdown' => Schema::hasColumn('trading_accounts', 'max_drawdown') ? 'max_drawdown' : null,
            'profit_loss' => Schema::hasColumn('trading_accounts', 'profit_loss') ? 'profit_loss' : null,
            'allowed_symbols' => Schema::hasColumn('trading_accounts', 'allowed_symbols') ? 'allowed_symbols' : null,
            'trial_status' => Schema::hasColumn('trading_accounts', 'trial_status') ? 'trial_status' : null,
            'trial_started_at' => Schema::hasColumn('trading_accounts', 'trial_started_at') ? 'trial_started_at' : null,
            'last_activity_at' => Schema::hasColumn('trading_accounts', 'last_activity_at') ? 'last_activity_at' : null,
            'ended_at' => Schema::hasColumn('trading_accounts', 'ended_at') ? 'ended_at' : null,
            'milestone_popup_3_shown' => Schema::hasColumn('trading_accounts', 'milestone_popup_3_shown') ? 'milestone_popup_3_shown' : null,
            'milestone_popup_5_shown' => Schema::hasColumn('trading_accounts', 'milestone_popup_5_shown') ? 'milestone_popup_5_shown' : null,
            'encouragement_email_sent_at' => Schema::hasColumn('trading_accounts', 'encouragement_email_sent_at') ? 'encouragement_email_sent_at' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('trading_accounts', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
