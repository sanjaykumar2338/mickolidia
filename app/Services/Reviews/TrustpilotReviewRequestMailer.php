<?php

namespace App\Services\Reviews;

use App\Mail\TrustpilotReviewRequestMail;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class TrustpilotReviewRequestMailer
{
    private const META_KEY = 'trustpilot_review';

    public function sendInitialIfNeeded(TradingAccount $account): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $payload = DB::transaction(function () use ($account): ?array {
            /** @var TradingAccount|null $freshAccount */
            $freshAccount = TradingAccount::query()
                ->with(['user', 'order'])
                ->lockForUpdate()
                ->find($account->id);

            if (! $freshAccount instanceof TradingAccount || ! $this->isEligibleForInitialRequest($freshAccount)) {
                return null;
            }

            $recipient = $this->recipientForAccount($freshAccount);

            if ($recipient === null) {
                return null;
            }

            $meta = $this->meta($freshAccount);

            if (Arr::has($meta, self::META_KEY.'.initial_requested_at')) {
                return null;
            }

            $sentAt = now();
            Arr::set($meta, self::META_KEY.'.initial_requested_at', $sentAt->toIso8601String());

            if ($this->reminderEnabled()) {
                Arr::set($meta, self::META_KEY.'.reminder_due_at', $sentAt->copy()->addDays($this->reminderDelayDays())->toIso8601String());
            }

            $freshAccount->forceFill(['meta' => $meta])->save();

            return [
                'email' => $recipient['email'],
                'trader_name' => $recipient['name'],
            ];
        });

        if ($payload === null) {
            return false;
        }

        Mail::to($payload['email'])->send(new TrustpilotReviewRequestMail(
            traderName: $payload['trader_name'],
            reviewUrl: $this->reviewUrl(),
        ));

        return true;
    }

    public function sendReminderIfDue(TradingAccount $account): bool
    {
        if (! $this->enabled() || ! $this->reminderEnabled()) {
            return false;
        }

        $payload = DB::transaction(function () use ($account): ?array {
            /** @var TradingAccount|null $freshAccount */
            $freshAccount = TradingAccount::query()
                ->with(['user', 'order'])
                ->lockForUpdate()
                ->find($account->id);

            if (! $freshAccount instanceof TradingAccount || ! $this->isEligibleForInitialRequest($freshAccount)) {
                return null;
            }

            $meta = $this->meta($freshAccount);

            if (! Arr::has($meta, self::META_KEY.'.initial_requested_at')
                || Arr::has($meta, self::META_KEY.'.reminder_sent_at')) {
                return null;
            }

            $dueAt = $this->dateFromMeta(Arr::get($meta, self::META_KEY.'.reminder_due_at'));

            if ($dueAt === null || $dueAt->isFuture()) {
                return null;
            }

            $recipient = $this->recipientForAccount($freshAccount);

            if ($recipient === null) {
                return null;
            }

            Arr::set($meta, self::META_KEY.'.reminder_sent_at', now()->toIso8601String());
            $freshAccount->forceFill(['meta' => $meta])->save();

            return [
                'email' => $recipient['email'],
                'trader_name' => $recipient['name'],
            ];
        });

        if ($payload === null) {
            return false;
        }

        Mail::to($payload['email'])->send(new TrustpilotReviewRequestMail(
            traderName: $payload['trader_name'],
            reviewUrl: $this->reviewUrl(),
            reminder: true,
        ));

        return true;
    }

    public function sendDueReminders(int $limit = 100): int
    {
        if (! $this->enabled() || ! $this->reminderEnabled()) {
            return 0;
        }

        $sent = 0;
        $limit = max(1, $limit);

        TradingAccount::query()
            ->where('is_trial', false)
            ->whereIn('challenge_status', ['passed', 'failed'])
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (TradingAccount $account) use (&$sent): void {
                if ($this->sendReminderIfDue($account)) {
                    $sent++;
                }
            });

        return $sent;
    }

    public function reviewUrl(): string
    {
        return (string) config('wolforix.review_requests.trustpilot.url', 'https://de.trustpilot.com/review/wolforix.com');
    }

    private function enabled(): bool
    {
        return (bool) config('wolforix.review_requests.trustpilot.enabled', true);
    }

    private function reminderEnabled(): bool
    {
        return (bool) config('wolforix.review_requests.trustpilot.reminder_enabled', true);
    }

    private function reminderDelayDays(): int
    {
        return max(1, (int) config('wolforix.review_requests.trustpilot.reminder_delay_days', 7));
    }

    private function isEligibleForInitialRequest(TradingAccount $account): bool
    {
        if ($account->is_trial || ! in_array($account->challenge_status, ['passed', 'failed'], true)) {
            return false;
        }

        return match ($account->challenge_status) {
            'passed' => $account->passed_email_sent_at !== null || $account->funded_pass_email_sent_at !== null,
            'failed' => $account->failed_email_sent_at !== null,
            default => false,
        };
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
     * @return array<string, mixed>
     */
    private function meta(TradingAccount $account): array
    {
        return is_array($account->meta) ? $account->meta : [];
    }

    private function dateFromMeta(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }
}
