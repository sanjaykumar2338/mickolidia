<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Services\MetaApi\MetaApiOnboardingService;
use Illuminate\Console\Command;

class TestOnboardingEvents extends Command
{
    protected $signature = 'wolforix:test-onboarding-events
        {login : MT5 login/account reference to test}
        {--dry-run : Show event readiness without writing test records}
        {--json : Print JSON output}';

    protected $description = 'Record safe Phase 2 onboarding event hook test records for future provider integrations.';

    public function handle(MetaApiOnboardingService $onboardingService): int
    {
        $login = trim((string) $this->argument('login'));
        $account = $this->accountForLogin($login);

        if (! $account instanceof TradingAccount) {
            $this->error('trading_account_missing');

            return self::FAILURE;
        }

        $eventTypes = [
            'account_assigned',
            'account_connected',
            'ready_to_trade',
            'account_stale',
            'account_disconnected',
            'account_recovered',
            'challenge_breached',
            'account_disabled',
            'onboarding_completed',
        ];

        if (! (bool) $this->option('dry-run')) {
            foreach ($eventTypes as $eventType) {
                $account = $onboardingService->recordEvent($account, 'test_'.$eventType, [
                    'message' => "Provider hook test prepared for {$eventType}.",
                    'login' => $login,
                    'external_delivery' => 'not_sent_by_test_command',
                ], 'test_'.$eventType);
            }
        }

        $diagnostic = $onboardingService->diagnose($account);

        $this->info('Phase 2 onboarding event hook test');
        $this->line((bool) $this->option('dry-run')
            ? 'Dry run only. No event records or external provider calls were written.'
            : 'No external Discord, Telegram, email, or CRM webhook was sent by this test command.');
        $this->line('Prepared providers: email, discord, telegram, crm_webhook.');

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode([
                'login' => $login,
                'dry_run' => (bool) $this->option('dry-run'),
                'event_types' => $eventTypes,
                'diagnostic' => $diagnostic,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[diagnostic unavailable]');
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
