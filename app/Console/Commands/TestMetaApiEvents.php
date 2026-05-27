<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Services\MetaApi\MetaApiAccountLifecycleService;
use Illuminate\Console\Command;

class TestMetaApiEvents extends Command
{
    protected $signature = 'wolforix:test-metaapi-events
        {login : MT5 login/account reference to test}
        {--json : Print JSON output}';

    protected $description = 'Record safe MetaApi lifecycle/event hook test records for future provider integrations.';

    public function handle(MetaApiAccountLifecycleService $lifecycleService): int
    {
        $login = trim((string) $this->argument('login'));
        $account = $this->accountForLogin($login);

        if (! $account instanceof TradingAccount) {
            $this->error('trading_account_missing');

            return self::FAILURE;
        }

        $eventTypes = [
            'account_connected',
            'account_disconnected',
            'account_stale',
            'account_recovered',
            'challenge_breached',
            'challenge_passed',
            'sync_failure',
            'account_activated',
        ];

        foreach ($eventTypes as $eventType) {
            $account = $lifecycleService->recordEvent($account, 'test_'.$eventType, [
                'message' => "Provider hook test prepared for {$eventType}.",
                'login' => $login,
                'external_delivery' => 'not_sent_by_test_command',
            ], 'test_'.$eventType);
        }

        $diagnostic = $lifecycleService->diagnose($account);
        $providers = (array) ($diagnostic['providers'] ?? []);

        $this->info('MetaApi event hook test');
        $this->line('No external Discord, Telegram, email, or CRM webhook was sent by this test command.');
        $this->line('Prepared providers: email, discord, telegram, crm_webhook.');
        $this->newLine();
        $this->table(['Provider', 'Prepared', 'Enabled'], collect($providers)->map(fn (array $provider, string $name): array => [
            $name,
            ! empty($provider['prepared']) ? 'yes' : 'no',
            ! empty($provider['enabled']) ? 'yes' : 'no',
        ])->values()->all());

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode([
                'login' => $login,
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
                    ->orWhere('account_reference', 'like', '%'.$login.'%');
            })
            ->where(function ($query): void {
                $query->where('platform_slug', 'mt5')
                    ->orWhere('platform', 'MT5');
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
