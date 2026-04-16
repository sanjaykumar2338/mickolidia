<?php

namespace App\Services\TradingAccounts;

use App\Mail\ConsistencyAlertMail;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ConsistencyAlertMailer
{
    public function sendIfNeeded(TradingAccount $account): void
    {
        if ($account->is_trial) {
            return;
        }

        $payload = DB::transaction(function () use ($account): ?array {
            /** @var TradingAccount|null $freshAccount */
            $freshAccount = TradingAccount::query()
                ->with(['user', 'order'])
                ->lockForUpdate()
                ->find($account->id);

            if (! $freshAccount instanceof TradingAccount) {
                return null;
            }

            $recipient = $this->recipientForAccount($freshAccount);

            if ($recipient === null) {
                return null;
            }

            $consistency = (array) data_get($freshAccount->rule_state, 'consistency', []);
            $status = (string) ($freshAccount->consistency_status ?: ($consistency['status'] ?? 'clear'));

            if (! in_array($status, ['approaching', 'breach'], true)) {
                return null;
            }

            if ($status === 'breach' && $freshAccount->consistency_breach_email_sent_at !== null) {
                return null;
            }

            if (
                $status === 'approaching'
                && ($freshAccount->consistency_approach_email_sent_at !== null || $freshAccount->consistency_breach_email_sent_at !== null)
            ) {
                return null;
            }

            $sentAt = now();

            $updates = $status === 'breach'
                ? ['consistency_breach_email_sent_at' => $sentAt]
                : ['consistency_approach_email_sent_at' => $sentAt];

            $freshAccount->forceFill($updates)->save();

            $freshAccount->forceFill([
                'rule_state' => array_merge((array) ($freshAccount->rule_state ?? []), [
                    'consistency' => array_merge($consistency, [
                        'approach_email_sent_at' => ($status === 'approaching'
                            ? $sentAt
                            : $freshAccount->consistency_approach_email_sent_at)?->toIso8601String(),
                        'breach_email_sent_at' => ($status === 'breach'
                            ? $sentAt
                            : $freshAccount->consistency_breach_email_sent_at)?->toIso8601String(),
                    ]),
                ]),
            ])->save();

            return [
                'email' => $recipient['email'],
                'trader_name' => $recipient['name'],
                'account' => $freshAccount->fresh(['user', 'order']) ?? $freshAccount,
                'details' => $this->mailDetails($freshAccount, $status),
            ];
        });

        if ($payload === null) {
            return;
        }

        Mail::to($payload['email'])->send(new ConsistencyAlertMail(
            traderName: $payload['trader_name'],
            tradingAccount: $payload['account'],
            details: $payload['details'],
        ));
    }

    /**
     * @return array{email:string,name:string}|null
     */
    private function recipientForAccount(TradingAccount $account): ?array
    {
        $order = $account->order;
        $user = $account->user;
        $email = $order instanceof Order && filled($order->email)
            ? (string) $order->email
            : ($user instanceof User && filled($user->email) ? (string) $user->email : null);

        if ($email === null) {
            return null;
        }

        return [
            'email' => $email,
            'name' => $order instanceof Order && filled($order->full_name)
                ? (string) $order->full_name
                : (string) ($user?->name ?: 'Trader'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mailDetails(TradingAccount $account, string $status): array
    {
        $consistency = (array) data_get($account->rule_state, 'consistency', []);
        $threshold = $status === 'breach'
            ? (float) ($consistency['breach_threshold_percent'] ?? $account->consistency_limit_percent ?? 40)
            : (float) ($consistency['approach_threshold_percent'] ?? max((float) ($account->consistency_limit_percent ?? 40) - 5, 0));

        return [
            'status' => $status,
            'rule_label' => $status === 'breach' ? 'Threshold reached' : 'Approaching threshold',
            'account_reference' => (string) ($account->account_reference ?: 'N/A'),
            'current_month_profit' => $this->money((float) ($consistency['current_month_profit'] ?? 0)),
            'highest_single_day_profit' => $this->money((float) ($consistency['highest_single_day_profit'] ?? 0)),
            'ratio_percent' => number_format((float) ($consistency['ratio_percent'] ?? 0), 2).'%',
            'threshold_percent' => number_format($threshold, 2).'%',
            'highest_single_day_date' => $this->formatDate($consistency['highest_single_day_date'] ?? null),
            'rule_explanation' => 'Wolforix flags accounts when one trading day produces a large share of the current month\'s realized profit. Profits should be distributed across multiple trading days.',
            'support_email' => (string) config('wolforix.support.email'),
        ];
    }

    private function money(float $value): string
    {
        return '$'.number_format($value, 2);
    }

    private function formatDate(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return 'N/A';
        }

        return Carbon::parse($value)->format('Y-m-d');
    }
}
