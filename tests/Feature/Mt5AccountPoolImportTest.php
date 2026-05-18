<?php

namespace Tests\Feature;

use App\Models\ChallengePlan;
use App\Models\ChallengePurchase;
use App\Models\Mt5AccountPoolEntry;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\TradingAccountSyncLog;
use App\Models\User;
use App\Services\Admin\AdminChallengeActivationService;
use App\Services\Mt5\Mt5AccountPoolImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class Mt5AccountPoolImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_service_handles_client_ods_structure_and_reports_skips(): void
    {
        Mt5AccountPoolEntry::factory()->internalOnly()->create([
            'login' => '770003',
            'server' => 'ICMarketsEU-Demo',
        ]);

        $path = $this->createOds([
            ['Login', 'Password', 'Investor Password', 'Server', 'Account Size', 'C', 'Status', 'Created Date', '', ''],
            ['770001', 'pass-1', 'investor-1', 'ICMarketsEU-Demo', '10000', '$', 'available', '16.04.26'],
            ['770001', 'pass-duplicate', 'investor-duplicate', 'ICMarketsEU-Demo', '10000', '$', 'available', '16.04.26'],
            ['770002', 'pass-2', 'investor-2', '', '25000', '€', 'available', '16.04.26'],
            ['770003', 'pass-3', 'investor-3', 'ICMarketsEU-Demo', '5000', '$', 'available', '16.04.26'],
            ['770004', 'pass-4', 'investor-4', 'PepperstoneUK-Demo', '50000', '€', 'available', '16.04.26'],
        ]);

        $report = app(Mt5AccountPoolImportService::class)->import(
            path: $path,
            pool: Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            batch: 'batch-client-test',
        );

        $this->assertSame('Tabelle1', $report['sheet_name']);
        $this->assertSame(1, $report['header_row_number']);
        $this->assertSame(2, $report['imported']);
        $this->assertSame(3, $report['skipped']);
        $this->assertSame(2, $report['duplicates']);
        $this->assertSame('Login', $report['column_map'][0]['label']);
        $this->assertSame('login', $report['column_map'][0]['field']);
        $this->assertSame('Investor Password', $report['column_map'][2]['label']);
        $this->assertSame('investor_password', $report['column_map'][2]['field']);
        $this->assertSame('C', $report['column_map'][5]['label']);
        $this->assertSame('currency_symbol', $report['column_map'][5]['field']);
        $this->assertSame('Created Date', $report['column_map'][7]['label']);
        $this->assertSame('source_created_at', $report['column_map'][7]['field']);
        $this->assertSame('', $report['column_map'][8]['label']);
        $this->assertNull($report['column_map'][8]['field']);
        $this->assertSame(1, $report['skipped_reasons']['duplicate_in_file']);
        $this->assertSame(1, $report['skipped_reasons']['missing_server']);
        $this->assertSame(1, $report['skipped_reasons']['duplicate_existing_other_pool']);

        $this->assertDatabaseCount('mt5_account_pool_entries', 3);
        $this->assertDatabaseHas('mt5_account_pool_entries', [
            'login' => '770001',
            'server' => 'ICMarketsEU-Demo',
            'account_size' => 10000,
            'currency_code' => 'USD',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'source_batch' => 'batch-client-test',
            'is_available' => true,
        ]);
        $this->assertSame('investor-1', Mt5AccountPoolEntry::query()
            ->where('login', '770001')
            ->where('server', 'ICMarketsEU-Demo')
            ->value('investor_password'));
        $this->assertDatabaseHas('mt5_account_pool_entries', [
            'login' => '770004',
            'server' => 'PepperstoneUK-Demo',
            'account_size' => 50000,
            'currency_code' => 'EUR',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
        ]);
    }

    public function test_parser_handles_internal_sheet_preamble_metadata(): void
    {
        $path = $this->createOds([
            ['Created at: 14.04.2026', '', '', '', ''],
            ['', '', '', '', ''],
            ['Login', 'Password', 'Server', 'Account Size', 'Status'],
            ['52840325', 'secret-1', 'ICMarketsEU-Demo', '10000', 'available'],
        ]);

        $inspection = app(Mt5AccountPoolImportService::class)->inspect($path);

        $this->assertSame(3, $inspection['header_row_number']);
        $this->assertSame('14.04.2026', $inspection['metadata']['created_at']);
        $this->assertCount(1, $inspection['rows']);
        $this->assertSame('52840325', $inspection['rows'][0]['values']['login']);
        $this->assertSame('ICMarketsEU-Demo', $inspection['rows'][0]['values']['server']);
    }

    public function test_fusionmarkets_import_requires_both_passwords_updates_safely_and_disables_old_unassigned_entries(): void
    {
        $oldEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '770010',
            'server' => 'ICMarketsEU-Demo',
            'account_size' => 10000,
            'source_file' => 'Accounts List 2 Wolforix.ods',
        ]);

        $path = $this->createOds([
            ['Login', 'Password', 'Investor Password', 'Server', 'Account Size', 'C', 'Status', 'Created Date'],
            ['335374', 'fusion-main-1', 'fusion-investor-1', 'FusionMarkets-Demo', '10000', '$', 'available', '23.04.26'],
            ['335382', 'fusion-main-2', '', 'FusionMarkets-Demo', '5000', '$', 'available', '23.04.26'],
        ]);

        $report = app(Mt5AccountPoolImportService::class)->import(
            path: $path,
            pool: Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            batch: 'fusion-test-batch',
            options: [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
                'update_existing' => true,
                'require_investor_password' => true,
                'deactivate_other_client_entries' => true,
            ],
        );

        $this->assertSame(1, $report['created']);
        $this->assertSame(0, $report['updated']);
        $this->assertSame(1, $report['invalid']);
        $this->assertSame(1, $report['deactivated_old_entries']);
        $this->assertSame(1, $report['skipped_reasons']['missing_investor_password']);

        $fusionEntry = Mt5AccountPoolEntry::query()
            ->where('login', '335374')
            ->where('server', 'FusionMarkets-Demo')
            ->firstOrFail();

        $this->assertSame('fusion-main-1', $fusionEntry->password);
        $this->assertSame('fusion-investor-1', $fusionEntry->investor_password);
        $this->assertSame(Mt5AccountPoolEntry::BROKER_FUSION_MARKETS, data_get($fusionEntry->meta, 'broker'));
        $this->assertSame(Mt5AccountPoolEntry::PLATFORM_MT5, data_get($fusionEntry->meta, 'platform'));
        $this->assertFalse((bool) $oldEntry->fresh()->is_available);

        $updatedPath = $this->createOds([
            ['Login', 'Password', 'Investor Password', 'Server', 'Account Size', 'C', 'Status', 'Created Date'],
            ['335374', 'fusion-main-updated', 'fusion-investor-updated', 'FusionMarkets-Demo', '10000', '$', 'available', '23.04.26'],
        ]);

        $secondReport = app(Mt5AccountPoolImportService::class)->import(
            path: $updatedPath,
            pool: Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            batch: 'fusion-test-batch-2',
            options: [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
                'update_existing' => true,
                'require_investor_password' => true,
                'deactivate_other_client_entries' => true,
            ],
        );

        $this->assertSame(0, $secondReport['created']);
        $this->assertSame(1, $secondReport['updated']);
        $this->assertSame(2, Mt5AccountPoolEntry::query()->count());
        $this->assertSame('fusion-main-updated', $fusionEntry->fresh()->password);
        $this->assertSame('fusion-investor-updated', $fusionEntry->fresh()->investor_password);
    }

    public function test_show_mt5_credentials_masks_by_default_and_reveals_only_with_flag(): void
    {
        $user = User::factory()->create();
        $account = TradingAccount::query()->create([
            'user_id' => $user->id,
            'account_reference' => 'WFX-MT5-00057-8HN7',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => '335405',
            'platform_account_id' => '335405',
            'account_type' => 'challenge',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'status' => 'Active',
        ]);

        Mt5AccountPoolEntry::factory()->create([
            'login' => '335405',
            'server' => 'FusionMarkets-Demo',
            'password' => 'REAL_PASSWORD',
            'investor_password' => 'REAL_INVESTOR_PASSWORD',
            'account_size' => 10000,
            'allocated_trading_account_id' => $account->id,
            'allocated_user_id' => $user->id,
            'allocated_at' => now(),
        ]);

        $this->artisan('wolforix:show-mt5-credentials', [
            'login' => '335405',
            '--account-reference' => 'WFX-MT5-00057-8HN7',
        ])
            ->expectsOutputToContain('Secrets are masked.')
            ->expectsOutputToContain('335405')
            ->expectsOutputToContain('FusionMarkets-Demo')
            ->expectsOutputToContain('WFX-MT5-00057-8HN7')
            ->doesntExpectOutputToContain('REAL_PASSWORD')
            ->doesntExpectOutputToContain('REAL_INVESTOR_PASSWORD')
            ->assertSuccessful();

        $this->artisan('wolforix:show-mt5-credentials', [
            'login' => '335405',
            '--account-reference' => 'WFX-MT5-00057-8HN7',
            '--show-secret' => true,
        ])
            ->expectsOutputToContain('Sensitive credentials shown once, do not share publicly')
            ->expectsOutputToContain('REAL_PASSWORD')
            ->expectsOutputToContain('REAL_INVESTOR_PASSWORD')
            ->assertSuccessful();
    }

    public function test_show_mt5_credentials_refuses_other_targets(): void
    {
        $this->artisan('wolforix:show-mt5-credentials', [
            'login' => '335406',
            '--account-reference' => 'WFX-MT5-00057-8HN7',
            '--show-secret' => true,
        ])
            ->expectsOutputToContain('Refusing to run')
            ->assertFailed();

        $this->artisan('wolforix:show-mt5-credentials', [
            'login' => '335405',
            '--account-reference' => 'WFX-MT5-OTHER',
            '--show-secret' => true,
        ])
            ->expectsOutputToContain('Refusing to run')
            ->assertFailed();
    }

    public function test_diagnose_mt5_credentials_masks_secrets_and_reports_integrity(): void
    {
        $user = User::factory()->create([
            'email' => 'client@example.test',
        ]);

        $account = TradingAccount::query()->create([
            'user_id' => $user->id,
            'account_reference' => 'WFX-MT5-00057-8HN7',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => '335405',
            'platform_account_id' => '335405',
            'platform_environment' => 'FusionMarkets-Demo',
            'account_type' => 'challenge',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'status' => 'Active',
            'meta' => [
                'mt5_sync' => [
                    'server' => 'FusionMarkets-Demo',
                    'broker' => 'FusionMarkets',
                ],
            ],
        ]);

        Mt5AccountPoolEntry::factory()->create([
            'login' => '335405',
            'server' => 'FusionMarkets-Demo',
            'password' => 'N7-live-master-83',
            'investor_password' => 'N7-live-investor-83',
            'account_size' => 10000,
            'allocated_trading_account_id' => $account->id,
            'allocated_user_id' => $user->id,
            'allocated_at' => now(),
            'meta' => [
                'broker' => 'FusionMarkets',
                'platform' => 'MT5',
            ],
        ]);

        TradingAccountSyncLog::query()->create([
            'trading_account_id' => $account->id,
            'platform' => 'mt5',
            'status' => 'success',
            'message' => 'MT5 metrics accepted',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'payload' => [
                'platform_login' => '335405',
                'platform_account_id' => '335405',
                'server' => 'FusionMarkets-Demo',
            ],
        ]);

        $this->artisan('wolforix:diagnose-mt5-credentials', [
            'login' => '335405',
            '--account-reference' => 'WFX-MT5-00057-8HN7',
        ])
            ->expectsOutputToContain('Read-only MT5 credential integrity diagnosis')
            ->expectsOutputToContain('source pool entry id')
            ->expectsOutputToContain('pass_raw_present')
            ->expectsOutputToContain('pass_cast_readable')
            ->expectsOutputToContain('pass_manual_readable')
            ->expectsOutputToContain('password equals REAL_PASSWORD')
            ->expectsOutputToContain('inv_raw_present')
            ->expectsOutputToContain('inv_cast_readable')
            ->expectsOutputToContain('inv_manual_readable')
            ->expectsOutputToContain('investor password equals REAL_INVESTOR_PASSWORD')
            ->expectsOutputToContain('All trading_accounts using platform_login/account_id 335405')
            ->expectsOutputToContain('auth success/accepted')
            ->expectsOutputToContain('Final decision: credentials look real')
            ->doesntExpectOutputToContain('N7-live-master-83')
            ->doesntExpectOutputToContain('N7-live-investor-83')
            ->assertSuccessful();

        $this->artisan('wolforix:diagnose-mt5-credentials', [
            'login' => '335405',
            '--account-reference' => 'WFX-MT5-00057-8HN7',
            '--show-secret' => true,
        ])
            ->expectsOutputToContain('N7-live-master-83')
            ->expectsOutputToContain('N7-live-investor-83')
            ->assertSuccessful();
    }

    public function test_diagnose_mt5_credentials_refuses_other_targets(): void
    {
        $this->artisan('wolforix:diagnose-mt5-credentials', [
            'login' => '335406',
            '--account-reference' => 'WFX-MT5-00057-8HN7',
            '--show-secret' => true,
        ])
            ->expectsOutputToContain('Refusing to run')
            ->assertFailed();

        $this->artisan('wolforix:diagnose-mt5-credentials', [
            'login' => '335405',
            '--account-reference' => 'WFX-MT5-OTHER',
            '--show-secret' => true,
        ])
            ->expectsOutputToContain('Refusing to run')
            ->assertFailed();
    }

    public function test_repair_mt5_credential_mapping_dry_run_does_not_change_rows(): void
    {
        [$wrongAccount, $correctAccount, $poolEntry] = $this->createMt5MappingRepairFixture();

        $this->artisan('wolforix:repair-mt5-credential-mapping')
            ->expectsOutputToContain('DRY RUN MT5 credential/account mapping repair')
            ->expectsOutputToContain('Dry run only. Re-run with --confirm')
            ->expectsOutputToContain('Trading account mapping before')
            ->expectsOutputToContain('Trading account mapping after')
            ->expectsOutputToContain('Pool entry mapping before')
            ->expectsOutputToContain('Pool entry mapping after')
            ->expectsOutputToContain('pending_credential_repair')
            ->doesntExpectOutputToContain('REAL_PASSWORD')
            ->doesntExpectOutputToContain('REAL_INVESTOR_PASSWORD')
            ->assertSuccessful();

        $this->assertSame('335405', $wrongAccount->fresh()->platform_login);
        $this->assertSame('335405', $wrongAccount->fresh()->platform_account_id);
        $this->assertSame('REAL_PASSWORD', data_get($wrongAccount->fresh()->meta, 'credentials.password'));
        $this->assertSame('REAL_INVESTOR_PASSWORD', data_get($wrongAccount->fresh()->meta, 'credentials.investor_password'));
        $this->assertNull($correctAccount->fresh()->platform_login);
        $this->assertSame($wrongAccount->id, $poolEntry->fresh()->allocated_trading_account_id);
    }

    public function test_repair_mt5_credential_mapping_confirm_moves_login_and_marks_wrong_account_pending(): void
    {
        [$wrongAccount, $correctAccount, $poolEntry] = $this->createMt5MappingRepairFixture();

        $this->artisan('wolforix:repair-mt5-credential-mapping', [
            '--confirm' => true,
        ])
            ->expectsOutputToContain('CONFIRMED MT5 credential/account mapping repair')
            ->expectsOutputToContain('Repair applied')
            ->doesntExpectOutputToContain('REAL_PASSWORD')
            ->doesntExpectOutputToContain('REAL_INVESTOR_PASSWORD')
            ->assertSuccessful();

        $wrongAccount = $wrongAccount->fresh();
        $correctAccount = $correctAccount->fresh();
        $poolEntry = $poolEntry->fresh();

        $this->assertNull($wrongAccount->platform_login);
        $this->assertNull($wrongAccount->platform_account_id);
        $this->assertSame('pending_credential_repair', $wrongAccount->platform_status);
        $this->assertSame('pending', $wrongAccount->sync_status);
        $this->assertNull(data_get($wrongAccount->meta, 'credentials.password'));
        $this->assertNull(data_get($wrongAccount->meta, 'credentials.investor_password'));
        $this->assertSame('pending', data_get($wrongAccount->meta, 'mt5_credential_repair.status'));
        $this->assertSame('active', $wrongAccount->account_status);
        $this->assertSame('active', $wrongAccount->challenge_status);
        $this->assertNull($wrongAccount->passed_at);
        $this->assertNull($wrongAccount->failed_at);

        $this->assertSame('335405', $correctAccount->platform_login);
        $this->assertSame('335405', $correctAccount->platform_account_id);
        $this->assertSame('FusionMarkets-Demo', $correctAccount->platform_environment);
        $this->assertSame('pending_credential_repair', $correctAccount->platform_status);
        $this->assertSame('pending', data_get($correctAccount->meta, 'mt5_credential_repair.status'));
        $this->assertSame('335405', data_get($correctAccount->meta, 'mt5_sync.identifier'));
        $this->assertSame('WFX-MT5-00062-NSTY', data_get($correctAccount->meta, 'mt5_sync.account_reference'));

        $this->assertSame($correctAccount->id, $poolEntry->allocated_trading_account_id);
        $this->assertSame($correctAccount->user_id, $poolEntry->allocated_user_id);
        $this->assertFalse($poolEntry->is_available);
    }

    public function test_assign_fresh_mt5_demo_account_dry_run_does_not_change_rows_or_expose_passwords(): void
    {
        [$account, $placeholderEntry, $realEntry] = $this->createFreshMt5AssignmentFixture();

        $this->artisan('wolforix:assign-fresh-mt5-demo-account')
            ->expectsOutputToContain('DRY RUN fresh FusionMarkets MT5 demo account assignment')
            ->expectsOutputToContain('Dry run only. Re-run with --confirm')
            ->expectsOutputToContain('Trading account mapping before')
            ->expectsOutputToContain('Trading account mapping after')
            ->expectsOutputToContain('Final verification summary')
            ->expectsOutputToContain('pass_raw_present')
            ->expectsOutputToContain('pass_cast_readable')
            ->expectsOutputToContain('pass_manual_readable')
            ->expectsOutputToContain('real_value')
            ->expectsOutputToContain((string) $realEntry->id)
            ->doesntExpectOutputToContain('fresh-master-pass-57')
            ->doesntExpectOutputToContain('fresh-investor-pass-57')
            ->assertSuccessful();

        $account = $account->fresh();
        $placeholderEntry = $placeholderEntry->fresh();
        $realEntry = $realEntry->fresh();

        $this->assertNull($account->platform_login);
        $this->assertSame('pending_credential_repair', $account->platform_status);
        $this->assertSame('pending', data_get($account->meta, 'mt5_credential_repair.status'));
        $this->assertNull($realEntry->allocated_trading_account_id);
        $this->assertTrue($realEntry->is_available);
        $this->assertNull($placeholderEntry->allocated_trading_account_id);
    }

    public function test_assign_fresh_mt5_demo_account_confirm_assigns_real_unused_pool_entry(): void
    {
        [$account, $placeholderEntry, $realEntry] = $this->createFreshMt5AssignmentFixture();

        $this->artisan('wolforix:assign-fresh-mt5-demo-account', [
            '--confirm' => true,
        ])
            ->expectsOutputToContain('CONFIRMED fresh FusionMarkets MT5 demo account assignment')
            ->expectsOutputToContain('Assignment applied')
            ->expectsOutputToContain('trades_snapshots_touched')
            ->doesntExpectOutputToContain('fresh-master-pass-57')
            ->doesntExpectOutputToContain('fresh-investor-pass-57')
            ->assertSuccessful();

        $account = $account->fresh();
        $placeholderEntry = $placeholderEntry->fresh();
        $realEntry = $realEntry->fresh();

        $this->assertSame('335777', $account->platform_login);
        $this->assertSame('335777', $account->platform_account_id);
        $this->assertSame('FusionMarkets-Demo', $account->platform_environment);
        $this->assertSame('waiting_for_first_sync', $account->platform_status);
        $this->assertSame('pending', $account->sync_status);
        $this->assertNull(data_get($account->meta, 'mt5_credential_repair'));
        $this->assertSame('fresh-master-pass-57', data_get($account->meta, 'credentials.password'));
        $this->assertSame('fresh-investor-pass-57', data_get($account->meta, 'credentials.investor_password'));
        $this->assertSame('335777', data_get($account->meta, 'mt5_sync.identifier'));
        $this->assertSame('WFX-MT5-00057-8HN7', data_get($account->meta, 'mt5_sync.account_reference'));
        $this->assertSame($realEntry->id, data_get($account->meta, 'mt5_pool_entry.id'));
        $this->assertSame('active', $account->account_status);
        $this->assertSame('active', $account->challenge_status);
        $this->assertNull($account->passed_at);
        $this->assertNull($account->failed_at);

        $this->assertSame($account->id, $realEntry->allocated_trading_account_id);
        $this->assertSame($account->user_id, $realEntry->allocated_user_id);
        $this->assertNotNull($realEntry->allocated_at);
        $this->assertFalse($realEntry->is_available);
        $this->assertNull($placeholderEntry->allocated_trading_account_id);
        $this->assertTrue($placeholderEntry->is_available);
    }

    public function test_assign_fresh_mt5_demo_account_show_secret_prints_selected_passwords(): void
    {
        $this->createFreshMt5AssignmentFixture();

        $this->artisan('wolforix:assign-fresh-mt5-demo-account', [
            '--show-secret' => true,
        ])
            ->expectsOutputToContain('fresh-master-pass-57')
            ->expectsOutputToContain('fresh-investor-pass-57')
            ->assertSuccessful();
    }

    public function test_assign_fresh_mt5_demo_account_accepts_raw_encrypted_string_credentials(): void
    {
        $account = $this->createFreshMt5AssignmentTargetAccount();

        DB::table('mt5_account_pool_entries')->insert([
            'login' => '335779',
            'server' => 'FusionMarkets-Demo',
            'password' => Crypt::encryptString('raw-master-pass-57'),
            'investor_password' => Crypt::encryptString('raw-investor-pass-57'),
            'account_size' => 10000,
            'currency_code' => 'USD',
            'source_status' => 'available',
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'source_batch' => 'raw-encrypted-test',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'source_created_at' => now()->subDay()->toDateString(),
            'is_available' => true,
            'is_promo' => false,
            'meta' => json_encode([
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('wolforix:assign-fresh-mt5-demo-account', [
            '--confirm' => true,
        ])
            ->expectsOutputToContain('password_manual')
            ->expectsOutputToContain('real_value')
            ->doesntExpectOutputToContain('raw-master-pass-57')
            ->doesntExpectOutputToContain('raw-investor-pass-57')
            ->assertSuccessful();

        $account = $account->fresh();

        $this->assertSame('335779', $account->platform_login);
        $this->assertSame('raw-master-pass-57', data_get($account->meta, 'credentials.password'));
        $this->assertSame('raw-investor-pass-57', data_get($account->meta, 'credentials.investor_password'));
    }

    public function test_assign_fresh_mt5_demo_account_rejects_placeholder_only_credentials(): void
    {
        $this->createFreshMt5AssignmentTargetAccount();

        Mt5AccountPoolEntry::factory()->create([
            'login' => '335780',
            'server' => 'FusionMarkets-Demo',
            'password' => 'REAL_PASSWORD',
            'investor_password' => 'REAL_INVESTOR_PASSWORD',
            'allocated_trading_account_id' => null,
            'allocated_user_id' => null,
            'allocated_at' => null,
            'is_available' => true,
        ]);

        $this->artisan('wolforix:assign-fresh-mt5-demo-account')
            ->expectsOutputToContain('no unused FusionMarkets-Demo pool entry with real non-placeholder credentials')
            ->expectsOutputToContain('placeholder')
            ->assertFailed();
    }

    public function test_assign_fresh_mt5_demo_account_rejects_real_decrypt_failure(): void
    {
        $this->createFreshMt5AssignmentTargetAccount();

        DB::table('mt5_account_pool_entries')->insert([
            'login' => '335781',
            'server' => 'FusionMarkets-Demo',
            'password' => 'not-a-valid-laravel-encrypted-value',
            'investor_password' => 'also-not-valid',
            'account_size' => 10000,
            'currency_code' => 'USD',
            'source_status' => 'available',
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'source_batch' => 'decrypt-failure-test',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'source_created_at' => now()->subDay()->toDateString(),
            'is_available' => true,
            'is_promo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('wolforix:assign-fresh-mt5-demo-account')
            ->expectsOutputToContain('no unused FusionMarkets-Demo pool entry with real non-placeholder credentials')
            ->expectsOutputToContain('decrypt_failed')
            ->assertFailed();
    }

    public function test_import_fresh_mt5_credentials_dry_run_does_not_write_or_print_passwords(): void
    {
        $path = $this->createFreshMt5Csv([
            ['login', 'server', 'password', 'investor_password', 'account_size', 'broker'],
            ['336001', 'FusionMarkets-Demo', 'fresh-import-pass-1', 'fresh-import-investor-1', '10000', 'FusionMarkets'],
        ]);

        $this->artisan('wolforix:import-fresh-mt5-credentials', [
            '--file' => $path,
        ])
            ->expectsOutputToContain('DRY RUN fresh MT5 credential import')
            ->expectsOutputToContain('Import dry-run verification table')
            ->expectsOutputToContain('planned')
            ->doesntExpectOutputToContain('fresh-import-pass-1')
            ->doesntExpectOutputToContain('fresh-import-investor-1')
            ->assertSuccessful();

        $this->assertDatabaseMissing('mt5_account_pool_entries', [
            'login' => '336001',
            'server' => 'FusionMarkets-Demo',
        ]);
    }

    public function test_import_fresh_mt5_credentials_confirm_encrypts_and_verifies_with_current_key(): void
    {
        $path = $this->createFreshMt5Csv([
            ['login', 'server', 'password', 'investor_password', 'account_size', 'broker'],
            ['336002', 'FusionMarkets-Demo', 'fresh-import-pass-2', 'fresh-import-investor-2', '10000', 'FusionMarkets'],
        ]);

        $this->artisan('wolforix:import-fresh-mt5-credentials', [
            '--file' => $path,
            '--confirm' => true,
        ])
            ->expectsOutputToContain('CONFIRMED fresh MT5 credential import')
            ->expectsOutputToContain('Import verification table')
            ->expectsOutputToContain('saved')
            ->expectsOutputToContain('decrypt_ok')
            ->doesntExpectOutputToContain('fresh-import-pass-2')
            ->doesntExpectOutputToContain('fresh-import-investor-2')
            ->assertSuccessful();

        $entry = Mt5AccountPoolEntry::query()
            ->where('login', '336002')
            ->where('server', 'FusionMarkets-Demo')
            ->firstOrFail();

        $this->assertSame('fresh-import-pass-2', $entry->password);
        $this->assertSame('fresh-import-investor-2', $entry->investor_password);
        $this->assertNotSame('fresh-import-pass-2', $entry->getRawOriginal('password'));
        $this->assertSame('fresh-import-pass-2', Crypt::decryptString((string) $entry->getRawOriginal('password')));
        $this->assertTrue($entry->is_available);
        $this->assertNull($entry->allocated_trading_account_id);
    }

    public function test_import_fresh_mt5_credentials_rejects_placeholders_and_prints_secrets_only_with_flag(): void
    {
        $path = $this->createFreshMt5Csv([
            ['login', 'server', 'password', 'investor_password', 'account_size', 'broker'],
            ['336003', 'FusionMarkets-Demo', 'REAL_PASSWORD', 'REAL_INVESTOR_PASSWORD', '10000', 'FusionMarkets'],
            ['336004', 'FusionMarkets-Demo', 'fresh-import-pass-4', 'fresh-import-investor-4', '10000', 'FusionMarkets'],
        ]);

        $this->artisan('wolforix:import-fresh-mt5-credentials', [
            '--file' => $path,
            '--show-secret' => true,
        ])
            ->expectsOutputToContain('placeholder_password')
            ->expectsOutputToContain('fresh-import-pass-4')
            ->assertSuccessful();

        $this->assertDatabaseMissing('mt5_account_pool_entries', [
            'login' => '336003',
            'server' => 'FusionMarkets-Demo',
        ]);
        $this->assertDatabaseMissing('mt5_account_pool_entries', [
            'login' => '336004',
            'server' => 'FusionMarkets-Demo',
        ]);
    }

    public function test_import_fresh_mt5_credentials_updates_unallocated_existing_and_skips_allocated(): void
    {
        $unallocated = Mt5AccountPoolEntry::factory()->create([
            'login' => '336005',
            'server' => 'FusionMarkets-Demo',
            'password' => 'old-pass',
            'investor_password' => 'old-investor',
            'allocated_at' => null,
            'allocated_trading_account_id' => null,
            'allocated_user_id' => null,
            'is_available' => false,
        ]);
        $allocatedUser = User::factory()->create();
        $allocatedAccount = TradingAccount::query()->create([
            'user_id' => $allocatedUser->id,
            'account_reference' => 'WFX-MT5-ALLOCATED-IMPORT',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'account_type' => 'challenge',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'status' => 'Active',
        ]);
        $allocated = Mt5AccountPoolEntry::factory()->allocated()->create([
            'login' => '336006',
            'server' => 'FusionMarkets-Demo',
            'password' => 'allocated-pass',
            'investor_password' => 'allocated-investor',
            'allocated_trading_account_id' => $allocatedAccount->id,
            'allocated_user_id' => $allocatedUser->id,
        ]);
        $path = $this->createFreshMt5Csv([
            ['login', 'server', 'password', 'investor_password', 'account_size', 'broker'],
            ['336005', 'FusionMarkets-Demo', 'new-pass', 'new-investor', '10000', 'FusionMarkets'],
            ['336006', 'FusionMarkets-Demo', 'should-not-save', 'should-not-save-investor', '10000', 'FusionMarkets'],
        ]);

        $this->artisan('wolforix:import-fresh-mt5-credentials', [
            '--file' => $path,
            '--confirm' => true,
        ])
            ->expectsOutputToContain('update_unallocated')
            ->expectsOutputToContain('existing_entry_allocated')
            ->assertSuccessful();

        $this->assertSame('new-pass', $unallocated->fresh()->password);
        $this->assertSame('new-investor', $unallocated->fresh()->investor_password);
        $this->assertTrue($unallocated->fresh()->is_available);
        $this->assertSame('allocated-pass', $allocated->fresh()->password);
        $this->assertSame('allocated-investor', $allocated->fresh()->investor_password);
    }

    public function test_fusionmarkets_import_can_rewrite_existing_entry_with_wrong_key_encrypted_credentials(): void
    {
        $entry = Mt5AccountPoolEntry::factory()->create([
            'login' => '335400',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 10000,
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'is_promo' => true,
            'is_available' => false,
        ]);

        $wrongKeyEncryptedPayload = base64_encode((string) json_encode([
            'iv' => base64_encode(random_bytes(16)),
            'value' => base64_encode('encrypted-with-another-key'),
            'mac' => str_repeat('0', 64),
            'tag' => '',
        ]));

        DB::table('mt5_account_pool_entries')
            ->where('id', $entry->id)
            ->update([
                'password' => $wrongKeyEncryptedPayload,
                'investor_password' => $wrongKeyEncryptedPayload,
            ]);

        $path = $this->createOds([
            ['Login', 'Password', 'Investor Password', 'Server', 'Account Size', 'C', 'Status', 'Created Date', ''],
            ['335400', 'fusion-main-fixed', 'fusion-investor-fixed', 'FusionMarkets-Demo', '10000', '$', 'available', '23.04.26', 'Promo'],
        ]);

        $report = app(Mt5AccountPoolImportService::class)->import(
            path: $path,
            pool: Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            batch: 'fusion-reencrypt-test-batch',
            options: [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
                'update_existing' => true,
                'require_investor_password' => true,
            ],
        );

        $entry->refresh();

        $this->assertSame(0, $report['created']);
        $this->assertSame(1, $report['updated']);
        $this->assertSame('fusion-main-fixed', $entry->password);
        $this->assertSame('fusion-investor-fixed', $entry->investor_password);
        $this->assertSame(10000, $entry->account_size);
        $this->assertTrue((bool) $entry->is_promo);
    }

    public function test_fusionmarkets_import_creates_single_use_promo_codes_and_excludes_promo_accounts_from_normal_pool(): void
    {
        $path = $this->createOds([
            ['Login', 'Password', 'Investor Password', 'Server', 'Account Size', 'C', 'Status', 'Created Date', ''],
            ['335374', 'promo-main-1', 'promo-investor-1', 'FusionMarkets-Demo', '10000', '$', 'available', '23.04.26', 'Promo'],
            ['335400', 'promo-main-2', 'promo-investor-2', 'FusionMarkets-Demo', '10000', '$', 'available', '23.04.26', 'Promo'],
            ['335411', 'normal-main', 'normal-investor', 'FusionMarkets-Demo', '10000', '$', 'available', '23.04.26', ''],
        ]);

        $report = app(Mt5AccountPoolImportService::class)->import(
            path: $path,
            pool: Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            batch: 'fusion-promo-test-batch',
            options: [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
                'update_existing' => true,
                'require_investor_password' => true,
            ],
        );

        $this->assertSame(2, $report['promo_accounts']);
        $this->assertSame(2, $report['promo_codes_created']);
        $this->assertDatabaseHas('mt5_account_pool_entries', [
            'login' => '335374',
            'is_promo' => true,
            'is_available' => false,
        ]);
        $this->assertDatabaseHas('mt5_account_pool_entries', [
            'login' => '335411',
            'is_promo' => false,
            'is_available' => true,
        ]);
        $this->assertDatabaseHas('mt5_promo_codes', [
            'code' => 'WFXGIVE-335374',
            'mt5_login' => '335374',
            'used_at' => null,
        ]);
        $this->assertDatabaseHas('mt5_promo_codes', [
            'code' => 'WFXGIVE-335400',
            'mt5_login' => '335400',
            'used_at' => null,
        ]);
    }

    public function test_admin_activation_allocates_from_client_pool_only_and_ignores_internal_pool_entries(): void
    {
        $user = User::factory()->create([
            'name' => 'Allocation Trader',
            'email' => 'allocation-trader@example.com',
        ]);

        $plan = ChallengePlan::query()->create([
            'slug' => 'one-step-25000',
            'name' => '1-Step 25K',
            'account_size' => 25000,
            'currency' => 'USD',
            'entry_fee' => 159,
            'profit_target' => 10,
            'daily_loss_limit' => 4,
            'max_loss_limit' => 8,
            'steps' => 1,
            'profit_share' => 80,
            'first_payout_days' => 21,
            'minimum_trading_days' => 3,
            'payout_cycle_days' => 14,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => $plan->id,
            'email' => $user->email,
            'full_name' => $user->name,
            'street_address' => '25 Allocation Street',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
            'challenge_type' => 'one_step',
            'account_size' => 25000,
            'currency' => 'USD',
            'payment_provider' => 'stripe',
            'base_price' => 199,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'final_price' => 199,
            'payment_status' => Order::PAYMENT_PAID,
            'order_status' => Order::STATUS_COMPLETED,
        ]);

        $purchase = ChallengePurchase::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'challenge_plan_id' => $plan->id,
            'challenge_type' => 'one_step',
            'account_size' => 25000,
            'currency' => 'USD',
            'account_status' => 'pending_activation',
        ]);

        $account = TradingAccount::query()->create([
            'user_id' => $user->id,
            'challenge_plan_id' => $plan->id,
            'order_id' => $order->id,
            'challenge_purchase_id' => $purchase->id,
            'challenge_type' => 'one_step',
            'account_size' => 25000,
            'account_reference' => 'WFX-MT5-ALLOC-25000',
            'platform' => 'cTrader',
            'platform_slug' => 'ctrader',
            'platform_environment' => 'demo',
            'platform_status' => 'pending_link',
            'stage' => 'Challenge Step 1',
            'status' => 'Pending Activation',
            'account_type' => 'challenge',
            'account_phase' => 'challenge',
            'phase_index' => 1,
            'account_status' => 'pending_activation',
            'challenge_status' => 'pending_activation',
            'starting_balance' => 25000,
            'phase_starting_balance' => 25000,
            'phase_reference_balance' => 25000,
            'balance' => 25000,
            'equity' => 25000,
            'highest_equity_today' => 25000,
            'profit_target_percent' => 10,
            'profit_target_amount' => 2500,
            'daily_drawdown_limit_percent' => 4,
            'daily_drawdown_limit_amount' => 1000,
            'max_drawdown_limit_percent' => 8,
            'max_drawdown_limit_amount' => 2000,
            'minimum_trading_days' => 3,
            'trading_days_completed' => 0,
            'sync_status' => 'pending',
        ]);

        $internalEntry = Mt5AccountPoolEntry::factory()->internalOnly()->create([
            'login' => '880001',
            'password' => 'internal-pass',
            'server' => 'ICMarketsEU-Demo',
            'account_size' => 25000,
        ]);

        $oldClientEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '990000',
            'password' => 'old-client-pass',
            'server' => 'PepperstoneUK-Demo',
            'account_size' => 25000,
            'source_file' => 'Accounts List 2 Wolforix.ods',
        ]);

        $clientEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '990001',
            'password' => 'client-pass',
            'investor_password' => 'client-investor-pass',
            'server' => 'FusionMarkets-Demo',
            'account_size' => 25000,
            'source_file' => 'Account List FusionMarkets-Demo30.04.ods',
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'provider' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
            ],
        ]);

        $activatedAccount = app(AdminChallengeActivationService::class)->activate($user);
        $activatedAccount->refresh();
        $clientEntry->refresh();
        $internalEntry->refresh();
        $oldClientEntry->refresh();

        $this->assertSame('990001', $activatedAccount->platform_login);
        $this->assertSame('990001', $activatedAccount->platform_account_id);
        $this->assertSame('FusionMarkets-Demo', data_get($activatedAccount->meta, 'credentials.server'));
        $this->assertSame('client-pass', data_get($activatedAccount->meta, 'credentials.password'));
        $this->assertSame('client-investor-pass', data_get($activatedAccount->meta, 'credentials.investor_password'));
        $this->assertSame(Mt5AccountPoolEntry::SOURCE_POOL_CLIENT, data_get($activatedAccount->meta, 'mt5_pool_entry.source_pool'));
        $this->assertSame(Mt5AccountPoolEntry::BROKER_FUSION_MARKETS, data_get($activatedAccount->meta, 'broker'));
        $this->assertSame($account->id, $clientEntry->allocated_trading_account_id);
        $this->assertFalse((bool) $clientEntry->is_available);
        $this->assertNull($internalEntry->allocated_at);
        $this->assertTrue((bool) $internalEntry->is_available);
        $this->assertNull($oldClientEntry->allocated_at);
        $this->assertTrue((bool) $oldClientEntry->is_available);
        $this->assertSame('880001', $internalEntry->login);
        $this->assertSame($account->id, $activatedAccount->id);
    }

    /**
     * @return array{TradingAccount, TradingAccount, Mt5AccountPoolEntry}
     */
    private function createMt5MappingRepairFixture(): array
    {
        $wrongUser = User::factory()->create([
            'email' => 'wrong-client@example.test',
        ]);
        $correctUser = User::factory()->create([
            'email' => 'correct-client@example.test',
        ]);

        $wrongAccount = TradingAccount::query()->create([
            'user_id' => $wrongUser->id,
            'account_reference' => 'WFX-MT5-00057-8HN7',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => '335405',
            'platform_account_id' => '335405',
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'connected',
            'sync_status' => 'success',
            'account_type' => 'challenge',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'status' => 'Active',
            'meta' => [
                'credentials' => [
                    'server' => 'FusionMarkets-Demo',
                    'password' => 'REAL_PASSWORD',
                    'trading_password' => 'REAL_PASSWORD',
                    'investor_password' => 'REAL_INVESTOR_PASSWORD',
                    'readonly_password' => 'REAL_INVESTOR_PASSWORD',
                ],
                'mt5_sync' => [
                    'identifier' => '335405',
                    'account_reference' => 'WFX-MT5-00057-8HN7',
                    'server' => 'FusionMarkets-Demo',
                ],
                'mt5_pool_entry' => [
                    'id' => 999,
                ],
            ],
        ]);

        $correctAccount = TradingAccount::query()->create([
            'user_id' => $correctUser->id,
            'account_reference' => 'WFX-MT5-00062-NSTY',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'pending_link',
            'sync_status' => 'pending',
            'account_type' => 'challenge',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'status' => 'Active',
            'meta' => [],
        ]);

        $poolEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '335405',
            'server' => 'FusionMarkets-Demo',
            'password' => 'REAL_PASSWORD',
            'investor_password' => 'REAL_INVESTOR_PASSWORD',
            'account_size' => 10000,
            'allocated_trading_account_id' => $wrongAccount->id,
            'allocated_user_id' => $wrongUser->id,
            'allocated_at' => now()->subDay(),
            'is_available' => false,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
            ],
        ]);

        return [$wrongAccount, $correctAccount, $poolEntry];
    }

    /**
     * @return array{TradingAccount, Mt5AccountPoolEntry, Mt5AccountPoolEntry}
     */
    private function createFreshMt5AssignmentFixture(): array
    {
        $account = $this->createFreshMt5AssignmentTargetAccount();

        $placeholderEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '335776',
            'server' => 'FusionMarkets-Demo',
            'password' => 'REAL_PASSWORD',
            'investor_password' => 'REAL_INVESTOR_PASSWORD',
            'account_size' => 10000,
            'source_created_at' => now()->subDays(2),
            'is_available' => true,
            'allocated_trading_account_id' => null,
            'allocated_user_id' => null,
            'allocated_at' => null,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
            ],
        ]);

        $realEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '335777',
            'server' => 'FusionMarkets-Demo',
            'password' => 'fresh-master-pass-57',
            'investor_password' => 'fresh-investor-pass-57',
            'account_size' => 10000,
            'source_created_at' => now()->subDay(),
            'is_available' => true,
            'allocated_trading_account_id' => null,
            'allocated_user_id' => null,
            'allocated_at' => null,
            'meta' => [
                'broker' => Mt5AccountPoolEntry::BROKER_FUSION_MARKETS,
                'platform' => Mt5AccountPoolEntry::PLATFORM_MT5,
            ],
        ]);

        Mt5AccountPoolEntry::factory()->create([
            'login' => '335778',
            'server' => 'OtherBroker-Demo',
            'password' => 'other-master-pass',
            'investor_password' => 'other-investor-pass',
            'account_size' => 10000,
        ]);

        return [$account, $placeholderEntry, $realEntry];
    }

    private function createFreshMt5AssignmentTargetAccount(): TradingAccount
    {
        $user = User::factory()->create([
            'email' => 'fresh-client@example.test',
        ]);

        return TradingAccount::query()->create([
            'user_id' => $user->id,
            'account_reference' => 'WFX-MT5-00057-8HN7',
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_environment' => 'FusionMarkets-Demo',
            'platform_status' => 'pending_credential_repair',
            'sync_status' => 'pending',
            'account_type' => 'challenge',
            'challenge_type' => 'two_step',
            'account_size' => 10000,
            'starting_balance' => 10000,
            'phase_starting_balance' => 10000,
            'phase_reference_balance' => 10000,
            'balance' => 10000,
            'equity' => 10000,
            'account_status' => 'active',
            'challenge_status' => 'active',
            'status' => 'Active',
            'meta' => [
                'mt5_credential_repair' => [
                    'status' => 'pending',
                    'reason' => 'test_fixture',
                ],
                'mt5_sync' => [
                    'status' => 'pending_credential_repair',
                    'account_reference' => 'WFX-MT5-00057-8HN7',
                ],
            ],
        ]);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function createFreshMt5Csv(array $rows): string
    {
        $directory = storage_path('framework/testing');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.Str::uuid()->toString().'.csv';
        $handle = fopen($path, 'wb');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function createOds(array $rows): string
    {
        $directory = storage_path('framework/testing');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.Str::uuid()->toString().'.ods';
        $archive = new ZipArchive();
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('content.xml', $this->contentXml($rows));
        $archive->close();

        return $path;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function contentXml(array $rows): string
    {
        $rowXml = array_map(function (array $row): string {
            $cells = array_map(function (string $value): string {
                $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

                return sprintf(
                    '<table:table-cell office:value-type="string"><text:p>%s</text:p></table:table-cell>',
                    $escaped,
                );
            }, $row);

            return '<table:table-row>'.implode('', $cells).'</table:table-row>';
        }, $rows);

        return sprintf(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<office:document-content
    xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
    xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
    xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
    office:version="1.2">
    <office:body>
        <office:spreadsheet>
            <table:table table:name="Tabelle1">
                %s
            </table:table>
        </office:spreadsheet>
    </office:body>
</office:document-content>
XML, implode('', $rowXml));
    }
}
