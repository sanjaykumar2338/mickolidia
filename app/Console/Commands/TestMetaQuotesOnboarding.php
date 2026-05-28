<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Services\MetaApi\MetaApiOnboardingService;
use App\Services\MetaQuotes\MetaQuotesPoolProvisioningService;
use Illuminate\Console\Command;

class TestMetaQuotesOnboarding extends Command
{
    protected $signature = 'wolforix:test-metaquotes-onboarding
        {login : MT5 login/account reference/trading account id to inspect}
        {--server=FusionMarkets-Demo : Restrict pool readiness to a server}
        {--json : Print JSON report}';

    protected $description = 'Dry-run MetaQuotes pool onboarding readiness for one login/account.';

    public function handle(
        MetaQuotesPoolProvisioningService $provisioningService,
        MetaApiOnboardingService $onboardingService,
    ): int {
        $login = (string) $this->argument('login');
        $account = $provisioningService->resolveAccountForSubject($login);
        $poolEntry = Mt5AccountPoolEntry::query()
            ->where('login', $login)
            ->latest('allocated_at')
            ->latest('id')
            ->first();
        $diagnostic = $provisioningService->diagnose([
            'server' => $this->option('server'),
        ]);
        $assignment = $account instanceof TradingAccount
            ? $provisioningService->provisionForAccount($account, [
                'server' => $this->option('server'),
                'pool_login' => $poolEntry?->login === $login ? $login : null,
                'dry_run' => true,
                'source' => 'test_metaquotes_onboarding_command',
            ])
            : null;
        $onboarding = $account instanceof TradingAccount
            ? $onboardingService->diagnose($account)
            : null;
        $report = [
            'login' => $login,
            'trading_account_found' => $account instanceof TradingAccount,
            'pool_entry_found' => $poolEntry instanceof Mt5AccountPoolEntry,
            'trading_account' => $account instanceof TradingAccount ? [
                'id' => $account->id,
                'account_reference' => $account->account_reference,
                'platform_login' => $account->platform_login,
                'platform_environment' => $account->platform_environment,
            ] : null,
            'pool_entry' => $poolEntry instanceof Mt5AccountPoolEntry ? [
                'id' => $poolEntry->id,
                'login' => $poolEntry->login,
                'server' => $poolEntry->server,
                'allocated_trading_account_id' => $poolEntry->allocated_trading_account_id,
                'is_available' => (bool) $poolEntry->is_available,
                'metaapi_account_id_present' => filled((string) data_get($poolEntry->meta, 'metaapi_account_id')),
            ] : null,
            'pool_diagnostics' => $diagnostic,
            'assignment_dry_run' => $assignment,
            'onboarding' => $onboarding,
            'ready_to_trade' => (bool) data_get($onboarding, 'sync_readiness.ready_to_trade', false),
        ];

        $this->info('MetaQuotes onboarding test');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Login', $login],
            ['Trading account found', $report['trading_account_found'] ? 'yes' : 'no'],
            ['Pool entry found', $report['pool_entry_found'] ? 'yes' : 'no'],
            ['Assignment status', (string) data_get($report, 'assignment_dry_run.status', '-')],
            ['Onboarding state', (string) data_get($report, 'onboarding.onboarding_state', '-')],
            ['MetaApi UUID', data_get($report, 'onboarding.sync_readiness.metaapi_account_id') ? 'present' : 'missing'],
            ['Ready to trade', $report['ready_to_trade'] ? 'yes' : 'no'],
        ]);

        $this->printList('Onboarding warnings', (array) data_get($report, 'onboarding.warnings', []));
        $this->printList('Recommendations', array_values(array_unique(array_merge(
            (array) data_get($report, 'pool_diagnostics.recommendations', []),
            (array) data_get($report, 'assignment_dry_run.recommendations', []),
            (array) data_get($report, 'onboarding.recommendations', []),
        ))));

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[report unavailable]');
        }

        return $account instanceof TradingAccount ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function printList(string $label, array $items): void
    {
        if ($items === []) {
            return;
        }

        $this->newLine();
        $this->info($label);

        foreach ($items as $item) {
            $this->line('- '.(string) $item);
        }
    }
}
