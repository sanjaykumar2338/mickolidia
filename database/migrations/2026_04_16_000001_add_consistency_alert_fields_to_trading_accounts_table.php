<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_accounts', function (Blueprint $table): void {
            $table->string('consistency_status', 32)
                ->default('clear')
                ->after('consistency_limit_percent');
            $table->decimal('consistency_last_trigger_threshold', 5, 2)
                ->nullable()
                ->after('consistency_status');
            $table->timestamp('consistency_triggered_at')
                ->nullable()
                ->after('consistency_last_trigger_threshold');
            $table->timestamp('consistency_approach_email_sent_at')
                ->nullable()
                ->after('consistency_triggered_at');
            $table->timestamp('consistency_breach_email_sent_at')
                ->nullable()
                ->after('consistency_approach_email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('trading_accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'consistency_status',
                'consistency_last_trigger_threshold',
                'consistency_triggered_at',
                'consistency_approach_email_sent_at',
                'consistency_breach_email_sent_at',
            ]);
        });
    }
};
