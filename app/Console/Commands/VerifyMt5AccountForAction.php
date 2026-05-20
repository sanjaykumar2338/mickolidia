<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class VerifyMt5AccountForAction extends Command
{
    protected $signature = 'wolforix:verify-mt5-account-for-action
        {login : MT5 login to verify before any manual action}';

    protected $description = 'Read-only verification for an MT5 account before any manual Wolforix account action.';

    public function handle(): int
    {
        $login = trim((string) $this->argument('login'));
        $environment = (string) config('app.env');
        $dbConfig = DB::connection()->getConfig();

        $this->info('READ-ONLY MT5 account verification');
        $this->warn('No database writes, deactivation, invalidation, or status changes are performed by this command.');
        $this->table(['Environment field', 'Value'], [
            ['app_env', $environment],
            ['db_connection', (string) config('database.default')],
            ['db_host', $this->formatValue($dbConfig['host'] ?? '-')],
            ['db_port', $this->formatValue($dbConfig['port'] ?? '-')],
            ['db_database_config', $this->formatValue($dbConfig['database'] ?? '-')],
        ]);

        if ($environment !== 'production') {
            $this->warn('WARNING — not production environment');
        }

        try {
            $driver = (string) ($dbConfig['driver'] ?? config('database.default'));
            $databaseName = in_array($driver, ['mysql', 'mariadb'], true)
                ? (string) (DB::selectOne('select database() as db')->db ?? '')
                : (string) ($dbConfig['database'] ?? '');

            if (! in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::selectOne('select 1 as connected');
            }
        } catch (Throwable $exception) {
            $this->error('NOT SAFE — DB unavailable');
            $this->line('DB error: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('DB connection works.');
        $this->line('Connected database: '.($databaseName !== '' ? $databaseName : '(unknown)'));

        /** @var Collection<int, TradingAccount> $accounts */
        $accounts = TradingAccount::query()
            ->with(['user', 'order', 'challengePurchase', 'challengePlan'])
            ->where(function ($query) use ($login): void {
                $query->where('platform_login', $login)
                    ->orWhere('platform_account_id', $login);
            })
            ->orderBy('id')
            ->get();

        /** @var Collection<int, Mt5AccountPoolEntry> $poolEntries */
        $poolEntries = Mt5AccountPoolEntry::query()
            ->with(['allocatedUser', 'allocatedTradingAccount'])
            ->where('login', $login)
            ->orderBy('id')
            ->get();

        $this->printTradingAccounts($accounts);
        $this->printRuleState($accounts);
        $this->printPoolEntries($poolEntries);
        $this->printSafetyCheck($login, $environment, $accounts, $poolEntries);

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, TradingAccount>  $accounts
     */
    private function printTradingAccounts(Collection $accounts): void
    {
        $this->newLine();
        $this->info('Trading Account');

        if ($accounts->isEmpty()) {
            $this->line('No trading account rows map to this login.');

            return;
        }

        $this->table([
            'id',
            'email',
            'user_id',
            'account_reference',
            'platform_login',
            'platform_account_id',
            'phase',
            'status',
            'challenge_status',
            'platform_status',
            'failed_at',
            'trading_blocked',
            'final_state_locked',
            'balance',
            'equity',
            'order',
        ], $accounts->map(fn (TradingAccount $account): array => [
            (string) $account->id,
            (string) ($account->user?->email ?? '-'),
            $this->formatValue($account->user_id),
            (string) ($account->account_reference ?: '-'),
            (string) ($account->platform_login ?: '-'),
            (string) ($account->platform_account_id ?: '-'),
            $this->phaseLabel($account),
            (string) ($account->account_status ?: $account->status ?: '-'),
            (string) ($account->challenge_status ?: '-'),
            (string) ($account->platform_status ?: '-'),
            $this->formatDate($account->failed_at),
            $this->boolString((bool) $account->trading_blocked),
            $this->boolString((bool) $account->final_state_locked),
            $this->formatValue($account->balance),
            $this->formatValue($account->equity),
            $account->order
                ? sprintf('#%s %s/%s', $account->order->id, $account->order->order_number, $account->order->order_status)
                : '-',
        ])->all());

        $this->newLine();
        $this->info('Related challenge/order details');
        $this->table(['account_id', 'challenge', 'purchase', 'plan', 'order_email'], $accounts->map(fn (TradingAccount $account): array => [
            (string) $account->id,
            trim(sprintf(
                '%s / size %s / phase %s',
                (string) ($account->challenge_type ?: '-'),
                $this->formatValue($account->account_size),
                $this->phaseLabel($account),
            )),
            $account->challengePurchase
                ? sprintf('#%s %s/%s', $account->challengePurchase->id, $account->challengePurchase->account_status, $account->challengePurchase->funded_status ?: '-')
                : '-',
            $account->challengePlan
                ? sprintf('#%s %s', $account->challengePlan->id, $account->challengePlan->slug)
                : '-',
            (string) ($account->order?->email ?? '-'),
        ])->all());
    }

    /**
     * @param  Collection<int, TradingAccount>  $accounts
     */
    private function printRuleState(Collection $accounts): void
    {
        $this->newLine();
        $this->info('Rule State');

        if ($accounts->isEmpty()) {
            $this->line('No rule state available because no trading account row was found.');

            return;
        }

        foreach ($accounts as $account) {
            $ruleState = is_array($account->rule_state) ? $account->rule_state : [];
            $failureContext = is_array($account->failure_context) ? $account->failure_context : [];
            $mt5Deactivation = (array) data_get($account->meta, 'mt5_deactivation', []);
            $manualReviewMatches = $this->findNeedles([
                'rule_state' => $ruleState,
                'failure_context' => $failureContext,
                'mt5_deactivation' => $mt5Deactivation,
            ], ['manual_review', 'manual review', 'review_status', 'review status']);
            $scalpingMatches = $this->findNeedles([
                'rule_state' => $ruleState,
                'failure_context' => $failureContext,
                'mt5_deactivation' => $mt5Deactivation,
            ], ['scalping', 'anti_scalping', 'anti-scalping', 'duration_abuse', 'duration abuse', 'under_60', 'less_than_60']);
            $currentDeactivation = (array) data_get($account->meta, 'mt5_deactivation.current', []);

            $this->line('Account #'.$account->id.' / '.($account->account_reference ?: '-'));
            $this->table(['Field', 'Value'], [
                ['failure_reason', (string) ($account->failure_reason ?: '-')],
                ['failure_context', $this->jsonValue($failureContext)],
                ['rule_state', $this->jsonValue($ruleState)],
                ['manual_review_detected', $manualReviewMatches === [] ? 'no' : 'yes: '.implode(', ', $manualReviewMatches)],
                ['scalping_detected', $scalpingMatches === [] ? 'no' : 'yes: '.implode(', ', $scalpingMatches)],
                ['failure_state', $this->boolString($account->challenge_status === 'failed' || filled($account->failure_reason) || $account->failed_at !== null)],
                ['deactivation_pending', $this->boolString($this->hasActiveDeactivation($account))],
                ['mt5_deactivation.status', (string) ($currentDeactivation['status'] ?? '-')],
                ['mt5_deactivation.event', (string) ($currentDeactivation['event'] ?? '-')],
                ['mt5_deactivation', $this->jsonValue($mt5Deactivation)],
            ]);
        }
    }

    /**
     * @param  Collection<int, Mt5AccountPoolEntry>  $poolEntries
     */
    private function printPoolEntries(Collection $poolEntries): void
    {
        $this->newLine();
        $this->info('MT5 Pool State');

        if ($poolEntries->isEmpty()) {
            $this->line('No MT5 pool rows were found for this login.');

            return;
        }

        $this->table([
            'id',
            'login',
            'server',
            'source_status',
            'is_available',
            'allocated_user_id',
            'allocated_user_email',
            'allocated_trading_account_id',
            'allocated_account_reference',
            'allocated_at',
            'active',
        ], $poolEntries->map(fn (Mt5AccountPoolEntry $entry): array => [
            (string) $entry->id,
            (string) $entry->login,
            (string) ($entry->server ?: '-'),
            (string) ($entry->source_status ?: '-'),
            $this->boolString((bool) $entry->is_available),
            $this->formatValue($entry->allocated_user_id),
            (string) ($entry->allocatedUser?->email ?? '-'),
            $this->formatValue($entry->allocated_trading_account_id),
            (string) ($entry->allocatedTradingAccount?->account_reference ?? '-'),
            $this->formatDate($entry->allocated_at),
            $this->boolString($this->poolEntryLooksActive($entry)),
        ])->all());
    }

    /**
     * @param  Collection<int, TradingAccount>  $accounts
     * @param  Collection<int, Mt5AccountPoolEntry>  $poolEntries
     */
    private function printSafetyCheck(string $login, string $environment, Collection $accounts, Collection $poolEntries): void
    {
        $this->newLine();
        $this->info('Safety Check');

        $uniqueTradingAccountMapping = $accounts->count() === 1;
        $uniquePoolMapping = $poolEntries->count() === 1;
        $account = $uniqueTradingAccountMapping ? $accounts->first() : null;
        $reasons = [];

        if ($environment !== 'production') {
            $reasons[] = 'not production environment';
        }

        if (! $uniqueTradingAccountMapping) {
            $reasons[] = $accounts->isEmpty()
                ? 'no trading account maps to login '.$login
                : 'duplicate trading accounts map to login '.$login;
        }

        if ($poolEntries->count() > 1) {
            $reasons[] = 'duplicate MT5 pool entries map to login '.$login;
        }

        if ($account instanceof TradingAccount && (string) $account->platform_slug !== 'mt5') {
            $reasons[] = 'mapped account is not an MT5 account';
        }

        if ($account instanceof TradingAccount && $this->hasActiveDeactivation($account)) {
            $reasons[] = 'account already has active MT5 deactivation state';
        }

        if ($account instanceof TradingAccount && $this->isDisabled($account)) {
            $reasons[] = 'account already appears disabled';
        }

        $poolAllocatedToDifferentAccount = $poolEntries
            ->filter(fn (Mt5AccountPoolEntry $entry): bool => $entry->allocated_trading_account_id !== null
                && $account instanceof TradingAccount
                && (int) $entry->allocated_trading_account_id !== (int) $account->id)
            ->isNotEmpty();

        if ($poolAllocatedToDifferentAccount) {
            $reasons[] = 'pool entry allocation points to a different trading account';
        }

        $safeToDeactivate = $reasons === [] && $account instanceof TradingAccount;
        $decision = $safeToDeactivate
            ? 'SAFE PRECHECK — unique live MT5 mapping, no active disable state found'
            : 'NOT SAFE — '.implode('; ', $reasons);

        $this->table(['Check', 'Result'], [
            ['trading_account_rows', (string) $accounts->count()],
            ['mt5_pool_rows', (string) $poolEntries->count()],
            ['unique_account_mapping', $this->boolString($uniqueTradingAccountMapping)],
            ['unique_trading_account_mapping', $this->boolString($uniqueTradingAccountMapping)],
            ['unique_pool_mapping', $this->boolString($uniquePoolMapping)],
            ['safe_to_deactivate', $safeToDeactivate ? 'yes' : 'no'],
            ['decision', $decision],
        ]);
        $this->line('Decision: '.$decision);
        foreach ($reasons as $reason) {
            $this->line('Safety blocker: '.$reason);
        }

        $this->newLine();
        $this->info('Recommended Next Action');
        if ($safeToDeactivate) {
            $this->line('Recommendation only: proceed to a separate reviewed deactivation/violation workflow if business approval and evidence are complete.');
        } else {
            $this->line('Recommendation only: do not deactivate until the safety blockers above are resolved.');
        }
    }

    private function hasActiveDeactivation(TradingAccount $account): bool
    {
        $status = (string) data_get($account->meta, 'mt5_deactivation.current.status', '');

        if ($status !== '' && $status !== 'disabled') {
            return true;
        }

        foreach ((array) data_get($account->meta, 'mt5_deactivation.events', []) as $event) {
            if (! is_array($event)) {
                continue;
            }

            $eventStatus = (string) ($event['status'] ?? '');
            if ($eventStatus !== '' && $eventStatus !== 'disabled') {
                return true;
            }
        }

        return false;
    }

    private function isDisabled(TradingAccount $account): bool
    {
        return in_array((string) $account->platform_status, ['disabled', 'disable_pending_ack', 'disable_requested'], true)
            || (string) data_get($account->meta, 'mt5_deactivation.current.status', '') === 'disabled';
    }

    private function poolEntryLooksActive(Mt5AccountPoolEntry $entry): bool
    {
        return (bool) $entry->is_available
            || $entry->allocated_trading_account_id !== null
            || $entry->allocated_user_id !== null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $needles
     * @return list<string>
     */
    private function findNeedles(array $payload, array $needles): array
    {
        $matches = [];
        $this->walkNeedles($payload, '', $needles, $matches);

        return array_values(array_unique($matches));
    }

    /**
     * @param  list<string>  $needles
     * @param  list<string>  $matches
     */
    private function walkNeedles(mixed $value, string $path, array $needles, array &$matches): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $childPath = $path === '' ? (string) $key : $path.'.'.$key;
                $this->walkNeedles($child, $childPath, $needles, $matches);
            }

            return;
        }

        $haystack = strtolower($path.' '.(is_scalar($value) ? (string) $value : ''));

        foreach ($needles as $needle) {
            if (str_contains($haystack, strtolower($needle))) {
                $matches[] = $path;
                break;
            }
        }
    }

    private function phaseLabel(TradingAccount $account): string
    {
        return sprintf(
            '%s / index %s',
            (string) ($account->account_phase ?: $account->stage ?: '-'),
            (string) ((int) $account->phase_index),
        );
    }

    private function boolString(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    private function formatDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $this->formatValue($value);
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $this->boolString($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return $this->jsonValue($value);
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function jsonValue(array $value): string
    {
        if ($value === []) {
            return '-';
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[json encode failed]';
    }
}
