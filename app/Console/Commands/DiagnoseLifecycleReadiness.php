<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Services\MetaApi\MetaApiAccountLifecycleService;
use App\Services\MetaApi\MetaApiOnboardingService;
use Illuminate\Console\Command;

class DiagnoseLifecycleReadiness extends Command
{
    protected $signature = 'wolforix:diagnose-lifecycle-readiness
        {login : MT5 login/account reference to inspect}
        {--json : Print JSON diagnostics}';

    protected $description = 'Inspect combined Phase 1 lifecycle and Phase 2 onboarding readiness.';

    public function handle(MetaApiAccountLifecycleService $lifecycleService, MetaApiOnboardingService $onboardingService): int
    {
        $login = trim((string) $this->argument('login'));
        $account = $this->accountForLogin($login);

        if (! $account instanceof TradingAccount) {
            $this->error('trading_account_missing');

            return self::FAILURE;
        }

        $diagnostic = [
            'lifecycle' => $lifecycleService->diagnose($account),
            'onboarding' => $onboardingService->diagnose($account),
        ];

        $this->info('MetaApi lifecycle readiness diagnostics');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Login', (string) data_get($diagnostic, 'lifecycle.login', $login)],
            ['Lifecycle state', (string) data_get($diagnostic, 'lifecycle.lifecycle_state', '-')],
            ['Sync health', (string) data_get($diagnostic, 'lifecycle.sync_health', '-')],
            ['Onboarding state', (string) data_get($diagnostic, 'onboarding.onboarding_state', '-')],
            ['Ready to trade', data_get($diagnostic, 'onboarding.sync_readiness.ready_to_trade') ? 'yes' : 'no'],
            ['Pool assigned', data_get($diagnostic, 'onboarding.assignment_status.assigned') ? 'yes' : 'no'],
            ['Breach locked', data_get($diagnostic, 'lifecycle.breach_state.final_state_locked') ? 'yes' : 'no'],
            ['Recovery count', (string) data_get($diagnostic, 'lifecycle.recovery.recovery_count', 0)],
        ]);

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
}
