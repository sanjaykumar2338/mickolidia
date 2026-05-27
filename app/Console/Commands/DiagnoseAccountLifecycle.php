<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Services\MetaApi\MetaApiAccountLifecycleService;
use Illuminate\Console\Command;

class DiagnoseAccountLifecycle extends Command
{
    protected $signature = 'wolforix:diagnose-account-lifecycle
        {login : MT5 login/account reference to inspect}
        {--json : Print JSON diagnostics}';

    protected $description = 'Inspect the MetaApi account lifecycle state without printing secrets.';

    public function handle(MetaApiAccountLifecycleService $lifecycleService): int
    {
        $login = trim((string) $this->argument('login'));
        $account = $this->accountForLogin($login);

        if (! $account instanceof TradingAccount) {
            $this->error('trading_account_missing');
            $this->line("No TradingAccount row is assigned to {$login}.");

            return self::FAILURE;
        }

        $diagnostic = $lifecycleService->diagnose($account);

        $this->info('MetaApi account lifecycle diagnosis');
        $this->line('Secrets are never printed by this command.');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Login', (string) ($diagnostic['login'] ?? $login)],
            ['Trading account', (string) ($diagnostic['trading_account_id'] ?? '-')],
            ['Account reference', (string) ($diagnostic['account_reference'] ?? '-')],
            ['Lifecycle state', (string) ($diagnostic['lifecycle_state'] ?? '-')],
            ['Sync health', (string) ($diagnostic['sync_health'] ?? '-')],
            ['Connector status', (string) ($diagnostic['connector_label'] ?? '-')],
            ['Last sync', (string) ($diagnostic['last_sync_at'] ?? '-')],
            ['Retry attempts', (string) data_get($diagnostic, 'retry_state.attempts', 0)],
            ['Next retry', (string) (data_get($diagnostic, 'retry_state.next_retry_at') ?: '-')],
            ['Breached', ! empty(data_get($diagnostic, 'breach_state.breached')) ? 'yes' : 'no'],
            ['Failure reason', (string) (data_get($diagnostic, 'breach_state.reason') ?: '-')],
            ['Recovery count', (string) data_get($diagnostic, 'recovery.recovery_count', 0)],
            ['Last recovered', (string) (data_get($diagnostic, 'recovery.last_recovered_at') ?: '-')],
        ]);

        $this->printEvents((array) ($diagnostic['recent_events'] ?? []));

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

    /**
     * @param  array<int, mixed>  $events
     */
    private function printEvents(array $events): void
    {
        if ($events === []) {
            return;
        }

        $this->newLine();
        $this->info('Recent lifecycle events');
        $this->table(['Time', 'Type', 'Message'], collect($events)->map(fn (mixed $event): array => [
            (string) data_get($event, 'occurred_at', '-'),
            (string) data_get($event, 'type', '-'),
            (string) data_get($event, 'context.message', '-'),
        ])->all());
    }
}
