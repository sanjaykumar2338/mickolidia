<?php

namespace Tests\Feature;

use App\Models\ChallengePlan;
use App\Models\ChallengePurchase;
use App\Models\Mt5AccountPoolEntry;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\User;
use App\Services\Admin\AdminChallengeActivationService;
use App\Services\Mt5\Mt5AccountPoolImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ['Login', 'Password', 'Server', 'Account Size', 'C', 'Status', 'Created Date', '', ''],
            ['770001', 'pass-1', 'ICMarketsEU-Demo', '10000', '$', 'available', '16.04.26'],
            ['770001', 'pass-duplicate', 'ICMarketsEU-Demo', '10000', '$', 'available', '16.04.26'],
            ['770002', 'pass-2', '', '25000', '€', 'available', '16.04.26'],
            ['770003', 'pass-3', 'ICMarketsEU-Demo', '5000', '$', 'available', '16.04.26'],
            ['770004', 'pass-4', 'PepperstoneUK-Demo', '50000', '€', 'available', '16.04.26'],
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
        $this->assertSame('C', $report['column_map'][4]['label']);
        $this->assertSame('currency_symbol', $report['column_map'][4]['field']);
        $this->assertSame('Created Date', $report['column_map'][6]['label']);
        $this->assertSame('source_created_at', $report['column_map'][6]['field']);
        $this->assertSame('', $report['column_map'][7]['label']);
        $this->assertNull($report['column_map'][7]['field']);
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

        $clientEntry = Mt5AccountPoolEntry::factory()->create([
            'login' => '990001',
            'password' => 'client-pass',
            'server' => 'PepperstoneUK-Demo',
            'account_size' => 25000,
        ]);

        $activatedAccount = app(AdminChallengeActivationService::class)->activate($user);
        $activatedAccount->refresh();
        $clientEntry->refresh();
        $internalEntry->refresh();

        $this->assertSame('990001', $activatedAccount->platform_login);
        $this->assertSame('990001', $activatedAccount->platform_account_id);
        $this->assertSame('PepperstoneUK-Demo', data_get($activatedAccount->meta, 'credentials.server'));
        $this->assertSame('client-pass', data_get($activatedAccount->meta, 'credentials.password'));
        $this->assertSame(Mt5AccountPoolEntry::SOURCE_POOL_CLIENT, data_get($activatedAccount->meta, 'mt5_pool_entry.source_pool'));
        $this->assertSame($account->id, $clientEntry->allocated_trading_account_id);
        $this->assertFalse((bool) $clientEntry->is_available);
        $this->assertNull($internalEntry->allocated_at);
        $this->assertTrue((bool) $internalEntry->is_available);
        $this->assertSame('880001', $internalEntry->login);
        $this->assertSame($account->id, $activatedAccount->id);
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
