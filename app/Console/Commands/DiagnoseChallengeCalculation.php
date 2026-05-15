<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Models\TradingAccountBalanceSnapshot;
use App\Models\TradingAccountSyncLog;
use App\Support\ChallengeCalculationBreakdown;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseChallengeCalculation extends Command
{
    protected $signature = 'wolforix:diagnose-challenge-calculation
        {account_reference : Wolforix trading account reference}';

    protected $description = 'Read-only diagnosis for MT5 challenge balance, target, and breach calculations.';

    public function handle(ChallengeCalculationBreakdown $calculationBreakdown): int
    {
        $accountReference = trim((string) $this->argument('account_reference'));

        $this->info('Read-only challenge calculation diagnosis');
        $this->line('Connection: '.config('database.default').' / database: '.(DB::connection()->getDatabaseName() ?: '(unknown)'));
        $this->newLine();

        $account = TradingAccount::query()
            ->with(['user', 'order', 'challengePurchase', 'challengePlan'])
            ->where('account_reference', $accountReference)
            ->first();

        if (! $account instanceof TradingAccount) {
            $this->error('Trading account was not found for account_reference '.$accountReference.'.');

            return self::FAILURE;
        }

        $latestSnapshot = $account->balanceSnapshots()
            ->orderByDesc('snapshot_at')
            ->orderByDesc('id')
            ->first();
        $latestSyncLog = $account->syncLogs()
            ->latest('id')
            ->first();
        $poolEntry = Mt5AccountPoolEntry::query()
            ->where('allocated_trading_account_id', $account->id)
            ->orWhere('login', (string) ($account->platform_login ?: $account->platform_account_id))
            ->latest('id')
            ->first();
        $calculation = $calculationBreakdown->forAccount($account, $latestSnapshot);

        $this->printAccountSources($account, $poolEntry, $latestSnapshot, $latestSyncLog);
        $this->printCalculation($calculation);
        $this->printRuleDecision($account, $calculation);
        $this->printLatestPayload($latestSnapshot, $latestSyncLog);
        $this->printRecentSnapshots($account);
        $this->printExclusionGuidance($account, $latestSnapshot);

        return self::SUCCESS;
    }

    private function printAccountSources(TradingAccount $account, ?Mt5AccountPoolEntry $poolEntry, ?TradingAccountBalanceSnapshot $snapshot, ?TradingAccountSyncLog $syncLog): void
    {
        $this->info('Account and baseline sources');
        $this->table(['Field', 'Value'], [
            ['user_email', (string) ($account->user?->email ?? '-')],
            ['trading_account_id', (string) $account->id],
            ['account_reference', (string) $account->account_reference],
            ['platform_login', (string) ($account->platform_login ?: '-')],
            ['platform_account_id', (string) ($account->platform_account_id ?: '-')],
            ['platform_status', (string) ($account->platform_status ?: '-')],
            ['status', (string) ($account->status ?: '-')],
            ['account_status', (string) ($account->account_status ?: '-')],
            ['challenge_status', (string) ($account->challenge_status ?: '-')],
            ['failure_reason', (string) ($account->failure_reason ?: '-')],
            ['trading_blocked', $this->yesNo((bool) $account->trading_blocked)],
            ['final_state_locked', $this->yesNo((bool) $account->final_state_locked)],
            ['challenge_type', (string) ($account->challenge_type ?: '-')],
            ['account_size', $this->moneyValue($account->account_size)],
            ['starting_balance', $this->moneyValue($account->starting_balance)],
            ['phase_starting_balance', $this->moneyValue($account->phase_starting_balance)],
            ['phase_reference_balance', $this->moneyValue($account->phase_reference_balance)],
            ['order.account_size', $this->moneyValue($account->order?->account_size)],
            ['purchase.account_size', $this->moneyValue($account->challengePurchase?->account_size)],
            ['plan.account_size', $this->moneyValue($account->challengePlan?->account_size)],
            ['pool_entry.id', $poolEntry ? (string) $poolEntry->id : '-'],
            ['pool_entry.login', $poolEntry ? (string) $poolEntry->login : '-'],
            ['pool_entry.account_size', $poolEntry ? $this->moneyValue($poolEntry->account_size) : '-'],
            ['latest_snapshot.id', $snapshot ? (string) $snapshot->id : '-'],
            ['latest_snapshot.snapshot_at', $this->formatValue($snapshot?->snapshot_at)],
            ['latest_sync_log.id', $syncLog ? (string) $syncLog->id : '-'],
            ['latest_sync_log.status', (string) ($syncLog?->status ?: '-')],
            ['latest_sync_log.completed_at', $this->formatValue($syncLog?->completed_at)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $calculation
     */
    private function printCalculation(array $calculation): void
    {
        $this->newLine();
        $this->info('Separated calculation values');
        $this->table(['Metric', 'Value'], [
            ['Raw MT5 Broker Balance', $this->moneyValue($calculation['raw_balance'])],
            ['Raw MT5 Broker Equity', $this->moneyValue($calculation['raw_equity'])],
            ['Broker phase reference balance', $this->moneyValue($calculation['broker_phase_reference_balance']).' ('.$calculation['broker_reference_source'].')'],
            ['Challenge starting balance', $this->moneyValue($calculation['challenge_starting_balance']).' ('.$calculation['challenge_starting_balance_source'].')'],
            ['Challenge balance', $this->moneyValue($calculation['challenge_balance'])],
            ['Challenge equity', $this->moneyValue($calculation['challenge_equity'])],
            ['Total realized P/L', $this->moneyValue($calculation['realized_profit'])],
            ['Floating P/L', $this->moneyValue($calculation['floating_pnl'])],
            ['Today P/L', $this->moneyValue($calculation['today_profit'])],
            ['Profit target percent', number_format((float) $calculation['profit_target_percent'], 2).'%'],
            ['Profit target amount', $this->moneyValue($calculation['profit_target_amount'])],
            ['Profit target progress', number_format((float) $calculation['profit_target_progress_percent'], 2).'%'],
            ['Profit target met', $this->yesNo((bool) $calculation['profit_target_met'])],
            ['Daily loss used', $this->moneyValue($calculation['daily_loss_used'])],
            ['Daily loss limit', $this->moneyValue($calculation['daily_loss_limit'])],
            ['Daily breach', $this->yesNo((bool) $calculation['daily_breach'])],
            ['Max drawdown used', $this->moneyValue($calculation['max_drawdown_used'])],
            ['Max drawdown limit', $this->moneyValue($calculation['max_drawdown_limit'])],
            ['Max breach', $this->yesNo((bool) $calculation['max_breach'])],
        ]);

        $this->newLine();
        $this->info('Formulas');
        $this->table(['Formula', 'Definition'], collect((array) $calculation['formula'])
            ->map(fn (string $definition, string $key): array => [$key, $definition])
            ->values()
            ->all());
    }

    /**
     * @param  array<string, mixed>  $calculation
     */
    private function printRuleDecision(TradingAccount $account, array $calculation): void
    {
        $this->newLine();
        $this->info('Diagnostic decision');

        if ((bool) $calculation['profit_target_met']) {
            $this->line('- Profit target is met by realized P/L under the current broker-reference calculation.');
        } else {
            $remaining = max((float) $calculation['profit_target_amount'] - (float) $calculation['realized_profit'], 0);
            $this->line('- Profit target is not met. Remaining realized P/L needed: '.$this->moneyValue($remaining).'.');
        }

        if ((bool) $calculation['breach']) {
            $this->line('- Breach condition is true: '.$calculation['breach_reason'].'.');
        } else {
            $this->line('- Breach condition is false. Backend should not mark this account failed from the current calculation.');
        }

        if ((string) $account->failure_reason !== '') {
            $this->line('- Stored failure_reason is currently '.$account->failure_reason.'.');
        }

        if ((bool) $account->trading_blocked || (bool) $account->final_state_locked) {
            $this->line('- Stored trading lock flags are set; review before allowing trading.');
        }
    }

    private function printLatestPayload(?TradingAccountBalanceSnapshot $snapshot, ?TradingAccountSyncLog $syncLog): void
    {
        $payload = is_array($snapshot?->payload) ? $snapshot->payload : (is_array($syncLog?->payload) ? $syncLog->payload : []);

        $this->newLine();
        $this->info('Latest payload summary');
        $this->table(['Payload field', 'Value'], [
            ['balance', $this->formatValue(data_get($payload, 'balance'))],
            ['equity', $this->formatValue(data_get($payload, 'equity'))],
            ['starting_balance', $this->formatValue(data_get($payload, 'starting_balance'))],
            ['broker_starting_balance', $this->formatValue(data_get($payload, 'broker_starting_balance'))],
            ['broker_phase_reference_balance', $this->formatValue(data_get($payload, 'broker_phase_reference_balance'))],
            ['today_profit', $this->formatValue(data_get($payload, 'today_profit'))],
            ['open_profit', $this->formatValue(data_get($payload, 'open_profit'))],
            ['positions_count', $this->formatValue(data_get($payload, 'positions_count'))],
            ['closed_positions_count', $this->formatValue(data_get($payload, 'closed_positions_count'))],
            ['trade_history_rows', is_array(data_get($payload, 'trade_history')) ? (string) count(data_get($payload, 'trade_history')) : '-'],
            ['open_positions_rows', is_array(data_get($payload, 'open_positions')) ? (string) count(data_get($payload, 'open_positions')) : '-'],
            ['sync_trigger', (string) data_get($payload, 'sync_trigger', '-')],
            ['timestamp', (string) (data_get($payload, 'timestamp') ?? data_get($payload, 'server_time') ?? '-')],
            ['server_day', (string) data_get($payload, 'server_day', '-')],
        ]);
    }

    private function printRecentSnapshots(TradingAccount $account): void
    {
        $snapshots = $account->balanceSnapshots()
            ->latest('id')
            ->limit(10)
            ->get();

        $this->newLine();
        $this->info('Recent persisted snapshots');
        $this->table(['id', 'snapshot_at', 'raw_balance', 'raw_equity', 'floating_p/l', 'total_profit', 'today_profit', 'trigger'], $snapshots->map(fn (TradingAccountBalanceSnapshot $snapshot): array => [
            (string) $snapshot->id,
            $this->formatValue($snapshot->snapshot_at),
            $this->moneyValue($snapshot->balance),
            $this->moneyValue($snapshot->equity),
            $this->moneyValue($snapshot->profit_loss),
            $this->moneyValue($snapshot->total_profit),
            $this->moneyValue($snapshot->today_profit),
            (string) data_get($snapshot->payload, 'sync_trigger', '-'),
        ])->all());
    }

    private function printExclusionGuidance(TradingAccount $account, ?TradingAccountBalanceSnapshot $snapshot): void
    {
        $excluded = (array) data_get($account->meta, 'calculation.excluded_internal_trade_tickets', []);

        $this->newLine();
        $this->info('Internal test trade note');
        $this->line('Excluded internal trade tickets configured: '.($excluded === [] ? 'none' : implode(', ', array_map('strval', $excluded))));
        $this->line('This diagnostic is read-only and does not mutate history.');

        $rows = is_array($snapshot?->payload) ? (array) data_get($snapshot->payload, 'trade_history', []) : [];
        if ($rows !== []) {
            $this->table(['ticket/deal', 'symbol', 'profit', 'close_time'], collect($rows)
                ->take(10)
                ->map(fn (mixed $row): array => is_array($row) ? [
                    (string) (data_get($row, 'ticket') ?? data_get($row, 'deal_id') ?? data_get($row, 'position_id') ?? '-'),
                    (string) data_get($row, 'symbol', '-'),
                    $this->formatValue(data_get($row, 'profit')),
                    (string) (data_get($row, 'close_time') ?? data_get($row, 'execution_timestamp') ?? '-'),
                ] : ['-', '-', '-', '-'])
                ->all());
        }
    }

    private function moneyValue(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '-';
        }

        return '$'.number_format((float) $value, 2);
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[array]';
        }

        return (string) $value;
    }
}
