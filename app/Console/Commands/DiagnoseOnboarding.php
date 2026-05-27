<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Services\MetaApi\MetaApiOnboardingService;
use Illuminate\Console\Command;

class DiagnoseOnboarding extends Command
{
    protected $signature = 'wolforix:diagnose-onboarding
        {login : MT5 login/account reference to inspect}
        {--json : Print JSON diagnostics}';

    protected $description = 'Inspect Phase 2 onboarding state, assignment readiness, retry status, and recommendations.';

    public function handle(MetaApiOnboardingService $onboardingService): int
    {
        $login = trim((string) $this->argument('login'));
        $account = $this->accountForLogin($login);

        if (! $account instanceof TradingAccount) {
            $this->error('trading_account_missing');

            return self::FAILURE;
        }

        $diagnostic = $onboardingService->diagnose($account);

        $this->info('Phase 2 onboarding diagnostics');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Login', (string) ($diagnostic['login'] ?? $login)],
            ['Trading account', (string) ($diagnostic['trading_account_id'] ?? '-')],
            ['Account reference', (string) ($diagnostic['account_reference'] ?? '-')],
            ['Onboarding state', (string) ($diagnostic['onboarding_state'] ?? '-')],
            ['Pool assigned', data_get($diagnostic, 'assignment_status.assigned') ? 'yes' : 'no'],
            ['Pool entry', (string) data_get($diagnostic, 'assignment_status.pool_entry_id', '-')],
            ['MetaApi account', (string) data_get($diagnostic, 'sync_readiness.metaapi_account_id', '-')],
            ['Last sync', (string) data_get($diagnostic, 'sync_readiness.last_sync_at', '-')],
            ['Ready to trade', data_get($diagnostic, 'sync_readiness.ready_to_trade') ? 'yes' : 'no'],
            ['Retry attempts', (string) data_get($diagnostic, 'retry_state.attempts', 0)],
            ['Next retry', (string) (data_get($diagnostic, 'retry_state.next_retry_at') ?: '-')],
            ['Recovery count', (string) data_get($diagnostic, 'recovery_state.recovery_count', 0)],
        ]);

        $this->printList('Warnings', (array) ($diagnostic['warnings'] ?? []));
        $this->printList('Recommendations', (array) ($diagnostic['recommendations'] ?? []));

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[diagnostic unavailable]');
        }

        return self::SUCCESS;
    }

    private function accountForLogin(string $login): ?TradingAccount
    {
        $account = TradingAccount::query()
            ->where(function ($query) use ($login): void {
                $query->where('platform_login', $login)
                    ->orWhere('platform_account_id', $login)
                    ->orWhere('account_reference', $login)
                    ->orWhere('account_reference', 'like', '%'.$login.'%')
                    ->orWhere('meta->mt5_sync->identifier', $login)
                    ->orWhere('meta->mt5_pool_entry->login', $login);
            })
            ->latest('id')
            ->first();

        if ($account instanceof TradingAccount) {
            return $account;
        }

        return Mt5AccountPoolEntry::query()
            ->where('login', $login)
            ->latest('allocated_at')
            ->latest('id')
            ->first()
            ?->allocatedTradingAccount;
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
