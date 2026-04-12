<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number')->nullable()->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('challenge_purchase_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('trading_account_id')->nullable()->constrained()->nullOnDelete();
                $table->string('currency', 3)->default('USD');
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('tax_amount', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->string('payment_method', 64)->nullable();
                $table->string('transaction_id')->nullable();
                $table->string('status', 32)->default('paid');
                $table->timestamp('issued_at')->nullable();
                $table->string('pdf_disk', 32)->default('public');
                $table->string('pdf_path')->nullable();
                $table->timestamp('pdf_generated_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trading_accounts')) {
            return;
        }

        $missingColumns = [
            'challenge_purchase_email_sent_at' => ! Schema::hasColumn('trading_accounts', 'challenge_purchase_email_sent_at'),
            'phase_one_pass_email_sent_at' => ! Schema::hasColumn('trading_accounts', 'phase_one_pass_email_sent_at'),
            'phase_two_credentials_email_sent_at' => ! Schema::hasColumn('trading_accounts', 'phase_two_credentials_email_sent_at'),
            'funded_pass_email_sent_at' => ! Schema::hasColumn('trading_accounts', 'funded_pass_email_sent_at'),
        ];

        if (! array_filter($missingColumns)) {
            return;
        }

        Schema::table('trading_accounts', function (Blueprint $table) use ($missingColumns) {
            if ($missingColumns['challenge_purchase_email_sent_at']) {
                $table->timestamp('challenge_purchase_email_sent_at')->nullable()->after('certificate_generated_at');
            }

            if ($missingColumns['phase_one_pass_email_sent_at']) {
                $table->timestamp('phase_one_pass_email_sent_at')->nullable()->after('challenge_purchase_email_sent_at');
            }

            if ($missingColumns['phase_two_credentials_email_sent_at']) {
                $table->timestamp('phase_two_credentials_email_sent_at')->nullable()->after('phase_one_pass_email_sent_at');
            }

            if ($missingColumns['funded_pass_email_sent_at']) {
                $table->timestamp('funded_pass_email_sent_at')->nullable()->after('phase_two_credentials_email_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('trading_accounts')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('trading_accounts', 'funded_pass_email_sent_at') ? 'funded_pass_email_sent_at' : null,
                Schema::hasColumn('trading_accounts', 'phase_two_credentials_email_sent_at') ? 'phase_two_credentials_email_sent_at' : null,
                Schema::hasColumn('trading_accounts', 'phase_one_pass_email_sent_at') ? 'phase_one_pass_email_sent_at' : null,
                Schema::hasColumn('trading_accounts', 'challenge_purchase_email_sent_at') ? 'challenge_purchase_email_sent_at' : null,
            ]));

            if ($columns !== []) {
                Schema::table('trading_accounts', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }

        Schema::dropIfExists('invoices');
    }
};
