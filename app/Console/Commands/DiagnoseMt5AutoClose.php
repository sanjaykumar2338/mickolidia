<?php

namespace App\Console\Commands;

use App\Models\TradingAccount;
use App\Models\TradingAccountBalanceSnapshot;
use App\Models\TradingAccountStatusHistory;
use App\Models\TradingAccountSyncLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DiagnoseMt5AutoClose extends Command
{
    protected $signature = 'wolforix:diagnose-mt5-auto-close
        {account_reference : Wolforix trading account reference}
        {--log-lines=80 : Maximum matching Laravel log lines to print}
        {--log-tail=3000 : Number of recent lines to scan from each Laravel log file}';

    protected $description = 'Read-only diagnosis for MT5 accounts whose EA is closing trades automatically.';

    public function handle(): int
    {
        $accountReference = trim((string) $this->argument('account_reference'));

        $this->info('Read-only MT5 auto-close diagnosis');
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

        $latestLog = TradingAccountSyncLog::query()
            ->where('trading_account_id', $account->id)
            ->latest('id')
            ->first();
        $latestPayload = is_array($latestLog?->payload) ? $latestLog->payload : [];
        $activeDeactivationEvent = $this->mt5DeactivationEvent($account);
        $visibleDeactivationEvent = $this->mt5DeactivationEvent($account, includeDisabled: true);

        $this->printAccountState($account);
        $this->printCurrentEaResponse($account, $activeDeactivationEvent, $visibleDeactivationEvent);
        $this->printRuleState($account);
        $this->printDeactivationState($account);
        $this->printLatestSyncLog($latestLog, $latestPayload, $account);
        $this->printRecentStatusHistory($account);
        $this->printRecentSnapshots($account);
        $this->printDecision($account, $activeDeactivationEvent);
        $this->printLogMatches($account, (int) $this->option('log-lines'), (int) $this->option('log-tail'));

        return self::SUCCESS;
    }

    private function printAccountState(TradingAccount $account): void
    {
        $this->info('Trading account state');
        $this->table(['Field', 'Value'], [
            ['user_email', (string) ($account->user?->email ?? '-')],
            ['trading_account_id', (string) $account->id],
            ['account_reference', (string) $account->account_reference],
            ['platform', (string) ($account->platform ?: '-')],
            ['platform_slug', (string) ($account->platform_slug ?: '-')],
            ['platform_login', (string) ($account->platform_login ?: '-')],
            ['platform_account_id', (string) ($account->platform_account_id ?: '-')],
            ['platform_status', (string) ($account->platform_status ?: '-')],
            ['status', (string) ($account->status ?: '-')],
            ['account_status', (string) ($account->account_status ?: '-')],
            ['challenge_status', (string) ($account->challenge_status ?: '-')],
            ['failure_reason', (string) ($account->failure_reason ?: '-')],
            ['trading_blocked', $this->yesNo((bool) $account->trading_blocked)],
            ['final_state_locked', $this->yesNo((bool) $account->final_state_locked)],
            ['phase_index', (string) ((int) $account->phase_index)],
            ['challenge_type', (string) ($account->challenge_type ?: '-')],
            ['account_size', (string) ($account->account_size ?: '-')],
            ['balance', $this->formatValue($account->balance)],
            ['equity', $this->formatValue($account->equity)],
            ['last_synced_at', $this->formatValue($account->last_synced_at)],
            ['last_evaluated_at', $this->formatValue($account->last_evaluated_at)],
            ['sync_status', (string) ($account->sync_status ?: '-')],
            ['sync_error', (string) ($account->sync_error ?: '-')],
        ]);
    }

    /**
     * @param  array{event:string,status:string,reason?:string,source?:string}|null  $activeEvent
     * @param  array{event:string,status:string,reason?:string,source?:string}|null  $visibleEvent
     */
    private function printCurrentEaResponse(TradingAccount $account, ?array $activeEvent, ?array $visibleEvent): void
    {
        $this->newLine();
        $this->info('Current EA response projection');
        $this->table(['Response field', 'Projected value'], [
            ['status', 'ok'],
            ['account_id', (string) $account->id],
            ['account_reference', (string) $account->account_reference],
            ['challenge_status', (string) ($account->challenge_status ?: '-')],
            ['phase_index', (string) ((int) $account->phase_index)],
            ['trading_days_completed', (string) ((int) $account->trading_days_completed)],
            ['failure_reason', (string) ($account->failure_reason ?: '-')],
            ['trading_blocked', $this->boolString((bool) $account->trading_blocked)],
            ['final_state_locked', $this->boolString((bool) $account->final_state_locked)],
            ['close_positions_required', $this->boolString($activeEvent !== null)],
            ['mt5_deactivation_required', $this->boolString($activeEvent !== null)],
            ['mt5_deactivation_event', (string) ($visibleEvent['event'] ?? '-')],
            ['mt5_deactivation_status', (string) ($visibleEvent['status'] ?? '-')],
            ['ea_action', $this->eaAction($account, $activeEvent)],
            ['ea_action_reason', $this->eaActionReason($account, $activeEvent)],
            ['last_synced_at', $this->formatValue($account->last_synced_at)],
        ]);
    }

    private function printRuleState(TradingAccount $account): void
    {
        $ruleState = is_array($account->rule_state) ? $account->rule_state : [];
        $failureContext = is_array($account->failure_context) ? $account->failure_context : [];

        $this->newLine();
        $this->info('Rule engine state');
        $this->table(['Rule field', 'Value'], [
            ['rule_state.failure_reason', (string) data_get($ruleState, 'failure_reason', '-')],
            ['daily_drawdown_breached', $this->formatValue(data_get($ruleState, 'daily_drawdown_breached'))],
            ['max_drawdown_breached', $this->formatValue(data_get($ruleState, 'max_drawdown_breached'))],
            ['daily_loss_used', $this->formatValue(data_get($ruleState, 'daily_loss_used', $account->daily_loss_used))],
            ['daily_loss_remaining', $this->formatValue(data_get($ruleState, 'daily_loss_remaining'))],
            ['daily_loss_threshold', $this->formatValue(data_get($ruleState, 'rules.daily_drawdown_limit_amount', $account->daily_drawdown_limit_amount))],
            ['max_drawdown_used', $this->formatValue(data_get($ruleState, 'max_drawdown_used', $account->max_drawdown_used))],
            ['max_drawdown_remaining', $this->formatValue(data_get($ruleState, 'max_drawdown_remaining'))],
            ['max_drawdown_threshold', $this->formatValue(data_get($ruleState, 'rules.max_drawdown_limit_amount', $account->max_drawdown_limit_amount))],
            ['phase_profit', $this->formatValue(data_get($ruleState, 'phase_profit', $account->total_profit))],
            ['profit_target_met', $this->formatValue(data_get($ruleState, 'profit_target_met'))],
            ['minimum_trading_days_met', $this->formatValue(data_get($ruleState, 'minimum_trading_days_met'))],
            ['trading_days_completed', $this->formatValue(data_get($ruleState, 'trading_days_completed', $account->trading_days_completed))],
            ['server_day', (string) data_get($ruleState, 'server_day', $this->formatValue($account->server_day))],
            ['evaluated_at', (string) data_get($ruleState, 'evaluated_at', $this->formatValue($account->last_evaluated_at))],
            ['failure_context.rule_breached', (string) data_get($failureContext, 'rule_breached', '-')],
            ['failure_context.threshold', $this->formatValue(data_get($failureContext, 'threshold'))],
            ['failure_context.recorded_value', $this->formatValue(data_get($failureContext, 'recorded_value'))],
            ['failure_context.breach_timestamp', (string) data_get($failureContext, 'breach_timestamp', '-')],
        ]);
    }

    private function printDeactivationState(TradingAccount $account): void
    {
        $current = data_get($account->meta, 'mt5_deactivation.current');
        $events = (array) data_get($account->meta, 'mt5_deactivation.events', []);

        $this->newLine();
        $this->info('MT5 deactivation / close-position state');

        if (is_array($current)) {
            $this->table(['Current field', 'Value'], $this->payloadRows($current));
        } else {
            $this->line('No mt5_deactivation.current payload found.');
        }

        if ($events === []) {
            $this->line('No mt5_deactivation.events payload found.');

            return;
        }

        $rows = [];
        foreach ($events as $eventKey => $event) {
            if (! is_array($event)) {
                continue;
            }

            $rows[] = [
                (string) $eventKey,
                (string) ($event['status'] ?? '-'),
                (string) ($event['reason'] ?? '-'),
                (string) ($event['failure_reason'] ?? '-'),
                (string) ($event['source'] ?? '-'),
                (string) ($event['requested_at'] ?? '-'),
                (string) ($event['acknowledged_at'] ?? '-'),
                (string) ($event['close_status'] ?? '-'),
                (string) ($event['positions_remaining_count'] ?? '-'),
                (string) ($event['last_error'] ?? $event['error'] ?? '-'),
            ];
        }

        $this->table([
            'event',
            'status',
            'reason',
            'failure_reason',
            'source',
            'requested_at',
            'acknowledged_at',
            'close_status',
            'remaining',
            'error',
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $latestPayload
     */
    private function printLatestSyncLog(?TradingAccountSyncLog $latestLog, array $latestPayload, TradingAccount $account): void
    {
        $this->newLine();
        $this->info('Latest MT5 sync log');

        if (! $latestLog instanceof TradingAccountSyncLog) {
            $this->line('No sync logs found for this trading account.');

            return;
        }

        $incomingLogin = (string) data_get($latestPayload, 'platform_login', '');
        $incomingAccountId = (string) data_get($latestPayload, 'platform_account_id', '');

        $this->table(['Field', 'Value'], [
            ['id', (string) $latestLog->id],
            ['status', (string) $latestLog->status],
            ['message', (string) ($latestLog->message ?: '-')],
            ['error_message', (string) ($latestLog->error_message ?: '-')],
            ['started_at', $this->formatValue($latestLog->started_at)],
            ['completed_at', $this->formatValue($latestLog->completed_at)],
            ['payload.sync_trigger', (string) data_get($latestPayload, 'sync_trigger', '-')],
            ['payload.platform_login', $incomingLogin !== '' ? $incomingLogin : '-'],
            ['payload.platform_account_id', $incomingAccountId !== '' ? $incomingAccountId : '-'],
            ['incoming_login_matches_stored', $this->identityMatches($incomingLogin, (string) ($account->platform_login ?: ''))],
            ['incoming_account_id_matches_stored', $this->identityMatches($incomingAccountId, (string) ($account->platform_account_id ?: ''))],
            ['payload.balance', $this->formatValue(data_get($latestPayload, 'balance'))],
            ['payload.equity', $this->formatValue(data_get($latestPayload, 'equity'))],
            ['payload.positions_count', $this->formatValue(data_get($latestPayload, 'positions_count'))],
            ['payload.closed_positions_count', $this->formatValue(data_get($latestPayload, 'closed_positions_count'))],
            ['payload.trading_blocked_ack', $this->formatValue(data_get($latestPayload, 'trading_blocked_ack'))],
            ['payload.close_success', $this->formatValue(data_get($latestPayload, 'close_success'))],
            ['payload.close_pending', $this->formatValue(data_get($latestPayload, 'close_pending'))],
            ['payload.positions_close_status', (string) data_get($latestPayload, 'positions_close_status', '-')],
            ['payload.close_result_message', (string) data_get($latestPayload, 'close_result_message', '-')],
            ['payload.keys', implode(', ', array_keys($latestPayload))],
        ]);

        $this->newLine();
        $this->info('Recent sync logs');
        $logs = TradingAccountSyncLog::query()
            ->where('trading_account_id', $account->id)
            ->latest('id')
            ->limit(10)
            ->get();

        $this->table(['id', 'status', 'message', 'error', 'completed_at', 'trigger', 'positions', 'closed', 'close_status'], $logs->map(function (TradingAccountSyncLog $log): array {
            $payload = is_array($log->payload) ? $log->payload : [];

            return [
                (string) $log->id,
                (string) $log->status,
                Str::limit((string) ($log->message ?: '-'), 60),
                Str::limit((string) ($log->error_message ?: '-'), 50),
                $this->formatValue($log->completed_at),
                (string) data_get($payload, 'sync_trigger', '-'),
                $this->formatValue(data_get($payload, 'positions_count')),
                $this->formatValue(data_get($payload, 'closed_positions_count')),
                (string) data_get($payload, 'positions_close_status', '-'),
            ];
        })->all());
    }

    private function printRecentStatusHistory(TradingAccount $account): void
    {
        $histories = TradingAccountStatusHistory::query()
            ->where('trading_account_id', $account->id)
            ->latest('id')
            ->limit(10)
            ->get();

        $this->newLine();
        $this->info('Recent account status history');

        if ($histories->isEmpty()) {
            $this->line('No status history rows found.');

            return;
        }

        $this->table(['id', 'previous', 'new', 'phase', 'source', 'changed_at', 'context_failure', 'daily_breach', 'max_breach'], $histories->map(fn (TradingAccountStatusHistory $history): array => [
            (string) $history->id,
            (string) ($history->previous_status ?: '-'),
            (string) ($history->new_status ?: '-'),
            ((string) $history->previous_phase_index).' -> '.((string) $history->new_phase_index),
            (string) ($history->source ?: '-'),
            $this->formatValue($history->changed_at),
            (string) data_get($history->context, 'failure_reason', '-'),
            $this->formatValue(data_get($history->context, 'daily_drawdown_breached')),
            $this->formatValue(data_get($history->context, 'max_drawdown_breached')),
        ])->all());
    }

    private function printRecentSnapshots(TradingAccount $account): void
    {
        $snapshots = TradingAccountBalanceSnapshot::query()
            ->where('trading_account_id', $account->id)
            ->latest('id')
            ->limit(5)
            ->get();

        $this->newLine();
        $this->info('Recent balance snapshots');

        if ($snapshots->isEmpty()) {
            $this->line('No balance snapshots found.');

            return;
        }

        $this->table(['id', 'snapshot_at', 'balance', 'equity', 'p/l', 'daily_dd', 'max_dd', 'trigger', 'positions'], $snapshots->map(fn (TradingAccountBalanceSnapshot $snapshot): array => [
            (string) $snapshot->id,
            $this->formatValue($snapshot->snapshot_at),
            $this->formatValue($snapshot->balance),
            $this->formatValue($snapshot->equity),
            $this->formatValue($snapshot->profit_loss),
            $this->formatValue($snapshot->daily_drawdown),
            $this->formatValue($snapshot->max_drawdown),
            (string) data_get($snapshot->payload, 'sync_trigger', '-'),
            $this->formatValue(data_get($snapshot->payload, 'positions_count')),
        ])->all());
    }

    /**
     * @param  array{event:string,status:string,reason?:string,source?:string}|null  $activeEvent
     */
    private function printDecision(TradingAccount $account, ?array $activeEvent): void
    {
        $this->newLine();
        $this->info('Diagnostic decision');

        $reasons = [];

        if ($activeEvent !== null) {
            $reasons[] = sprintf(
                'EA is being told to close positions because an active MT5 deactivation event exists: %s / %s.',
                $activeEvent['event'] ?: 'unknown',
                $activeEvent['status'] ?: 'unknown',
            );

            if (($activeEvent['reason'] ?? '') !== '') {
                $reasons[] = 'Deactivation reason: '.$activeEvent['reason'].'.';
            }

            if (($activeEvent['source'] ?? '') !== '') {
                $reasons[] = 'Deactivation source: '.$activeEvent['source'].'.';
            }
        } elseif ((bool) $account->trading_blocked) {
            $reasons[] = 'No active close-position event exists, but trading_blocked is true; EA action projects block_trading.';
        } else {
            $reasons[] = 'No active close-position event exists and trading_blocked is false; EA action projects continue.';
        }

        if (in_array((string) $account->challenge_status, ['failed', 'passed'], true) || (bool) $account->final_state_locked) {
            $reasons[] = 'The account is in a final state or final_state_locked is true.';
        }

        if ((string) data_get($account->rule_state, 'failure_reason', '') !== '') {
            $reasons[] = 'Rule engine failure reason: '.data_get($account->rule_state, 'failure_reason').'.';
        }

        foreach ($reasons as $reason) {
            $this->line('- '.$reason);
        }
    }

    private function printLogMatches(TradingAccount $account, int $maxLines, int $tailLines): void
    {
        $this->newLine();
        $this->info('Recent Laravel log matches');

        $logDir = storage_path('logs');
        if (! File::isDirectory($logDir)) {
            $this->line('No storage/logs directory found.');

            return;
        }

        $terms = array_values(array_filter([
            (string) $account->account_reference,
            (string) $account->id,
            (string) $account->platform_login,
            (string) $account->platform_account_id,
            'MT5 metrics EA action decision',
            'MT5 deactivation',
            'close_all_positions',
            'close_positions',
            'trading_blocked',
            'daily_loss_breached',
            'max_drawdown_breached',
            'Ignored stale MT5 metrics payload',
        ], static fn (string $term): bool => $term !== ''));

        $files = collect(File::files($logDir))
            ->filter(fn (\SplFileInfo $file): bool => Str::startsWith($file->getFilename(), 'laravel') && $file->isFile())
            ->sortByDesc(fn (\SplFileInfo $file): int => $file->getMTime())
            ->values();

        $matches = [];
        foreach ($files as $file) {
            foreach ($this->tailFile($file->getPathname(), max($tailLines, 1)) as $lineNumber => $line) {
                $lower = Str::lower($line);

                foreach ($terms as $term) {
                    if ($term !== '' && str_contains($lower, Str::lower($term))) {
                        $matches[] = [
                            'file:line' => $file->getFilename().':'.$lineNumber,
                            'line' => Str::limit($line, 260),
                        ];

                        break;
                    }
                }

                if (count($matches) >= max($maxLines, 0)) {
                    break 2;
                }
            }
        }

        if ($matches === []) {
            $this->line('No matching lines found in recent Laravel logs scanned.');

            return;
        }

        $this->table(['file:line', 'line'], array_map(static fn (array $row): array => [$row['file:line'], $row['line']], $matches));
    }

    /**
     * @return array<int, string>
     */
    private function tailFile(string $path, int $limit): array
    {
        if (! is_readable($path)) {
            return [];
        }

        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = max($file->key(), 0);
        $start = max($lastLine - $limit + 1, 0);
        $lines = [];

        for ($lineNumber = $start; $lineNumber <= $lastLine; $lineNumber++) {
            $file->seek($lineNumber);
            $line = trim((string) $file->current());

            if ($line !== '') {
                $lines[$lineNumber + 1] = $line;
            }
        }

        return $lines;
    }

    /**
     * @return array{event:string,status:string,reason?:string,source?:string}|null
     */
    private function mt5DeactivationEvent(TradingAccount $account, bool $includeDisabled = false): ?array
    {
        if ((string) $account->platform_slug !== 'mt5') {
            return null;
        }

        $current = data_get($account->meta, 'mt5_deactivation.current');

        if (is_array($current)) {
            $status = (string) ($current['status'] ?? '');

            if ($status !== '' && ($includeDisabled || $status !== 'disabled')) {
                return [
                    'event' => (string) ($current['event'] ?? ''),
                    'status' => $status,
                    'reason' => (string) ($current['reason'] ?? ''),
                    'source' => (string) ($current['source'] ?? ''),
                ];
            }
        }

        foreach ((array) data_get($account->meta, 'mt5_deactivation.events', []) as $eventKey => $event) {
            if (! is_array($event)) {
                continue;
            }

            $status = (string) ($event['status'] ?? '');

            if ($status === '' || (! $includeDisabled && $status === 'disabled')) {
                continue;
            }

            return [
                'event' => (string) $eventKey,
                'status' => $status,
                'reason' => (string) ($event['reason'] ?? ''),
                'source' => (string) ($event['source'] ?? ''),
            ];
        }

        return null;
    }

    /**
     * @param  array{event:string,status:string,reason?:string,source?:string}|null  $activeEvent
     */
    private function eaAction(TradingAccount $account, ?array $activeEvent): string
    {
        if ($activeEvent !== null) {
            return 'close_all_positions_and_disable_account';
        }

        if ((bool) $account->trading_blocked) {
            return 'block_trading';
        }

        return 'continue';
    }

    /**
     * @param  array{event:string,status:string,reason?:string,source?:string}|null  $activeEvent
     */
    private function eaActionReason(TradingAccount $account, ?array $activeEvent): string
    {
        if ($activeEvent !== null) {
            return sprintf(
                'active_mt5_deactivation_event:%s/%s',
                $activeEvent['event'] ?: 'unknown',
                $activeEvent['status'] ?: 'unknown',
            );
        }

        if ((bool) $account->trading_blocked) {
            return 'trading_blocked_without_active_deactivation_event';
        }

        return 'account_can_continue';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{0:string,1:string}>
     */
    private function payloadRows(array $payload): array
    {
        $rows = [];

        foreach ($payload as $key => $value) {
            $rows[] = [(string) $key, $this->formatValue($value)];
        }

        return $rows;
    }

    private function identityMatches(string $incoming, string $stored): string
    {
        if ($incoming === '') {
            return 'unknown; latest log has no incoming value';
        }

        if ($stored === '') {
            return 'unknown; stored value is empty';
        }

        return $incoming === $stored ? 'yes' : 'no';
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    private function boolString(bool $value): string
    {
        return $value ? 'true' : 'false';
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
