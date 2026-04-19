<?php

namespace App\Http\Controllers;

use App\Mail\TrustpilotReviewRequestMail;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\User;
use App\Services\Reviews\TrustpilotReviewRequestMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AdminReviewRequestController extends Controller
{
    public function index(TrustpilotReviewRequestMailer $mailer): View
    {
        $accounts = TradingAccount::query()
            ->with(['user', 'order', 'challengePlan'])
            ->where('is_trial', false)
            ->where(function ($query): void {
                $query->whereIn('challenge_status', ['passed', 'failed'])
                    ->orWhereNotNull('passed_email_sent_at')
                    ->orWhereNotNull('failed_email_sent_at')
                    ->orWhereNotNull('funded_pass_email_sent_at');
            })
            ->latest('updated_at')
            ->limit(200)
            ->get();

        $rows = $accounts->map(fn (TradingAccount $account): array => $this->reviewRow($account));

        return view('admin.reviews.index', [
            'reviewUrl' => $mailer->reviewUrl(),
            'config' => [
                'enabled' => (bool) config('wolforix.review_requests.trustpilot.enabled', true),
                'reminder_enabled' => (bool) config('wolforix.review_requests.trustpilot.reminder_enabled', true),
                'reminder_delay_days' => (int) config('wolforix.review_requests.trustpilot.reminder_delay_days', 7),
                'reminder_schedule_time' => (string) config('wolforix.review_requests.trustpilot.reminder_schedule_time', '10:00'),
            ],
            'summary' => $this->summary($rows),
            'reviewRows' => $rows,
        ]);
    }

    public function sendTest(Request $request, TrustpilotReviewRequestMailer $mailer): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'trader_name' => ['nullable', 'string', 'max:255'],
        ]);

        $email = (string) $validated['email'];
        $traderName = trim((string) ($validated['trader_name'] ?? '')) ?: 'Test Trader';

        try {
            Mail::to($email)->send(new TrustpilotReviewRequestMail(
                traderName: $traderName,
                reviewUrl: $mailer->reviewUrl(),
            ));
        } catch (\Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->only(['email', 'trader_name']))
                ->with('error', 'The Trustpilot test email could not be sent: '.$exception->getMessage());
        }

        return redirect()
            ->route('admin.reviews.index')
            ->with('status', 'Trustpilot test email sent to: '.$email);
    }

    public function sendDueReminders(Request $request, TrustpilotReviewRequestMailer $mailer): RedirectResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $sent = $mailer->sendDueReminders((int) ($validated['limit'] ?? 100));

        return redirect()
            ->route('admin.reviews.index')
            ->with('status', "Trustpilot reminders sent: {$sent}");
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function summary(Collection $rows): array
    {
        return [
            'tracked_accounts' => $rows->count(),
            'initial_sent' => $rows->whereNotNull('initial_requested_at_raw')->count(),
            'reminders_due' => $rows->where('reminder_status_key', 'due')->count(),
            'reminders_sent' => $rows->whereNotNull('reminder_sent_at_raw')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewRow(TradingAccount $account): array
    {
        $meta = is_array($account->meta) ? $account->meta : [];
        $initialRequestedAt = $this->dateFromMeta(Arr::get($meta, 'trustpilot_review.initial_requested_at'));
        $reminderDueAt = $this->dateFromMeta(Arr::get($meta, 'trustpilot_review.reminder_due_at'));
        $reminderSentAt = $this->dateFromMeta(Arr::get($meta, 'trustpilot_review.reminder_sent_at'));
        $trigger = $this->triggerDetails($account);
        $recipient = $this->recipientForAccount($account);
        $reminderStatus = $this->reminderStatus($initialRequestedAt, $reminderDueAt, $reminderSentAt);

        return [
            'user_name' => $recipient['name'],
            'user_email' => $recipient['email'],
            'profile_email' => $account->user?->email,
            'account_reference' => $account->account_reference ?? 'N/A',
            'plan' => $account->challengePlan?->name ?? $this->fallbackPlanLabel($account),
            'challenge_status' => $this->humanize((string) ($account->challenge_status ?: $account->account_status ?: 'pending')),
            'trigger_source' => $trigger['source'],
            'triggered_at' => $this->formatDateTime($trigger['at']),
            'initial_requested_at' => $this->formatDateTime($initialRequestedAt),
            'initial_requested_at_raw' => $initialRequestedAt,
            'reminder_due_at' => $this->formatDateTime($reminderDueAt),
            'reminder_sent_at' => $this->formatDateTime($reminderSentAt),
            'reminder_sent_at_raw' => $reminderSentAt,
            'reminder_status' => $reminderStatus['label'],
            'reminder_status_key' => $reminderStatus['key'],
            'reminder_status_class' => $reminderStatus['class'],
            'updated_at' => $this->formatDateTime($account->updated_at),
        ];
    }

    /**
     * @return array{source: string, at: Carbon|null}
     */
    private function triggerDetails(TradingAccount $account): array
    {
        if ($account->failed_email_sent_at !== null) {
            return [
                'source' => 'Challenge failed email',
                'at' => $account->failed_email_sent_at,
            ];
        }

        if ($account->funded_pass_email_sent_at !== null) {
            return [
                'source' => 'Funded pass email',
                'at' => $account->funded_pass_email_sent_at,
            ];
        }

        if ($account->passed_email_sent_at !== null) {
            return [
                'source' => 'Challenge passed email',
                'at' => $account->passed_email_sent_at,
            ];
        }

        return [
            'source' => 'Waiting for final email trigger',
            'at' => null,
        ];
    }

    /**
     * @return array{email: string, name: string}
     */
    private function recipientForAccount(TradingAccount $account): array
    {
        $order = $account->order;
        $user = $account->user;

        return [
            'email' => $order instanceof Order && filled($order->email)
                ? (string) $order->email
                : (string) ($user?->email ?: 'No recipient email'),
            'name' => $order instanceof Order && filled($order->full_name)
                ? (string) $order->full_name
                : (string) ($user instanceof User && filled($user->name) ? $user->name : 'Trader'),
        ];
    }

    /**
     * @return array{label: string, key: string, class: string}
     */
    private function reminderStatus(?Carbon $initialRequestedAt, ?Carbon $reminderDueAt, ?Carbon $reminderSentAt): array
    {
        if (! (bool) config('wolforix.review_requests.trustpilot.reminder_enabled', true)) {
            return [
                'label' => 'Disabled',
                'key' => 'disabled',
                'class' => 'border-slate-500/30 bg-slate-500/10 text-slate-300',
            ];
        }

        if ($initialRequestedAt === null) {
            return [
                'label' => 'Waiting for initial request',
                'key' => 'waiting',
                'class' => 'border-amber-400/25 bg-amber-400/10 text-amber-100',
            ];
        }

        if ($reminderSentAt !== null) {
            return [
                'label' => 'Reminder sent',
                'key' => 'sent',
                'class' => 'border-emerald-400/25 bg-emerald-500/10 text-emerald-100',
            ];
        }

        if ($reminderDueAt === null) {
            return [
                'label' => 'No reminder scheduled',
                'key' => 'not_scheduled',
                'class' => 'border-slate-500/30 bg-slate-500/10 text-slate-300',
            ];
        }

        if ($reminderDueAt->isFuture()) {
            return [
                'label' => 'Scheduled',
                'key' => 'scheduled',
                'class' => 'border-sky-400/25 bg-sky-500/10 text-sky-100',
            ];
        }

        return [
            'label' => 'Due now',
            'key' => 'due',
            'class' => 'border-rose-400/25 bg-rose-500/10 text-rose-100',
        ];
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
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function formatDateTime(?\DateTimeInterface $value): string
    {
        return $value instanceof \DateTimeInterface
            ? Carbon::instance($value)->format('Y-m-d H:i')
            : 'Not sent';
    }

    private function fallbackPlanLabel(TradingAccount $account): string
    {
        if ($account->challenge_type !== null && $account->account_size !== null) {
            return sprintf('%s / %dK', $this->humanize((string) $account->challenge_type), (int) ($account->account_size / 1000));
        }

        return 'N/A';
    }

    private function humanize(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }
}
