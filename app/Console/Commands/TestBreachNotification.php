<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use App\Services\MetaApi\MetaApiAccountLifecycleService;
use Illuminate\Console\Command;

class TestBreachNotification extends Command
{
    protected $signature = 'wolforix:test-breach-notification
        {login : MT5 login/account reference to test}
        {--json : Print JSON output}';

    protected $description = 'Record a safe MetaApi breach-notification test event without printing secrets.';

    public function handle(MetaApiAccountLifecycleService $lifecycleService): int
    {
        $login = trim((string) $this->argument('login'));
        $account = $this->accountForLogin($login);

        if (! $account instanceof TradingAccount) {
            $this->error('trading_account_missing');

            return self::FAILURE;
        }

        $message = $this->message($account);
        $account = $lifecycleService->recordEvent($account, 'breach_notification_tested', [
            'message' => $message,
            'login' => $login,
            'challenge_status' => $account->challenge_status,
            'failure_reason' => $account->failure_reason,
            'external_delivery' => 'not_sent_by_test_command',
        ]);
        $diagnostic = $lifecycleService->diagnose($account);

        $this->info('MetaApi breach notification test');
        $this->line('No external email, Discord, Telegram, or CRM webhook was sent by this test command.');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Login', $login],
            ['Trading account', '#'.$account->id],
            ['Challenge status', (string) ($account->challenge_status ?: '-')],
            ['Failure reason', (string) ($account->failure_reason ?: '-')],
            ['Lifecycle state', (string) ($diagnostic['lifecycle_state'] ?? '-')],
            ['Sync health', (string) ($diagnostic['sync_health'] ?? '-')],
            ['Message', $message],
        ]);

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode([
                'login' => $login,
                'message' => $message,
                'diagnostic' => $diagnostic,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[diagnostic unavailable]');
        }

        return self::SUCCESS;
    }

    private function message(TradingAccount $account): string
    {
        $rule = str((string) ($account->failure_reason ?: 'risk_rule'))->replace('_', ' ')->title()->toString();

        return "We respect the effort behind this challenge. A trading rule was violated ({$rule}), so the challenge cannot continue in its current phase. The account has been protected in its final state, and the next best step is to review the trade decisions calmly and prepare for stronger future participation.";
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
