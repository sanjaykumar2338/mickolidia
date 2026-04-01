<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trading_accounts') || Schema::hasColumn('trading_accounts', 'ctrader_connection_id')) {
            return;
        }

        Schema::table('trading_accounts', function (Blueprint $table): void {
            $table->foreignId('ctrader_connection_id')
                ->nullable()
                ->after('user_id')
                ->constrained('ctrader_connections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trading_accounts') || ! Schema::hasColumn('trading_accounts', 'ctrader_connection_id')) {
            return;
        }

        Schema::table('trading_accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ctrader_connection_id');
        });
    }
};
