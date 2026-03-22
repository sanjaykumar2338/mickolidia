<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_accounts', function (Blueprint $table) {
            $table->string('account_type')->default('challenge')->after('status');
            $table->boolean('is_trial')->default(false)->after('account_type');
            $table->decimal('equity', 12, 2)->default(0)->after('balance');
            $table->decimal('daily_drawdown', 12, 2)->default(0)->after('equity');
            $table->decimal('max_drawdown', 12, 2)->default(0)->after('daily_drawdown');
            $table->decimal('profit_loss', 12, 2)->default(0)->after('max_drawdown');
            $table->json('allowed_symbols')->nullable()->after('trading_days_completed');
            $table->string('trial_status')->nullable()->after('allowed_symbols');
            $table->timestamp('trial_started_at')->nullable()->after('trial_status');
            $table->timestamp('last_activity_at')->nullable()->after('trial_started_at');
            $table->timestamp('ended_at')->nullable()->after('last_activity_at');
            $table->boolean('milestone_popup_3_shown')->default(false)->after('ended_at');
            $table->boolean('milestone_popup_5_shown')->default(false)->after('milestone_popup_3_shown');
            $table->timestamp('encouragement_email_sent_at')->nullable()->after('milestone_popup_5_shown');
        });
    }

    public function down(): void
    {
        Schema::table('trading_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'account_type',
                'is_trial',
                'equity',
                'daily_drawdown',
                'max_drawdown',
                'profit_loss',
                'allowed_symbols',
                'trial_status',
                'trial_started_at',
                'last_activity_at',
                'ended_at',
                'milestone_popup_3_shown',
                'milestone_popup_5_shown',
                'encouragement_email_sent_at',
            ]);
        });
    }
};
