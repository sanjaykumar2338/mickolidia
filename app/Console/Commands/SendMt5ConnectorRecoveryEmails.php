<?php

namespace App\Console\Commands;

use App\Mail\Mt5OnboardingSetupMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendMt5ConnectorRecoveryEmails extends Command
{
    protected $signature = 'wolforix:send-mt5-connector-recovery-emails
        {--dry-run : List recipients without sending}
        {--limit= : Maximum number of recipients to process}
        {--mailer=ceo : Mailer to use for the first send attempt}
        {--from-address=ceo@wolforix.com : Sender address for this recovery campaign}
        {--from-name=Wolforix CEO : Sender name for this recovery campaign}
        {--cc=ceo@wolforix.com : CC address for recovery confirmations}';

    protected $description = 'Send MT5 connector recovery onboarding emails to all registered MT5 clients and trial users.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->positiveIntegerOption('limit');
        $mailer = trim((string) $this->option('mailer')) ?: 'ceo';
        $fromAddress = trim((string) $this->option('from-address')) ?: 'ceo@wolforix.com';
        $fromName = trim((string) $this->option('from-name')) ?: 'Wolforix CEO';
        $cc = trim((string) $this->option('cc')) ?: 'ceo@wolforix.com';

        if (! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            $this->error('The from address is not a valid email address.');

            return self::FAILURE;
        }

        if (! filter_var($cc, FILTER_VALIDATE_EMAIL)) {
            $this->error('The CC address is not a valid email address.');

            return self::FAILURE;
        }

        $this->configureRecoverySender($mailer, $fromAddress, $fromName);

        $processed = 0;
        $sent = 0;
        $failed = 0;
        $seenEmails = [];

        $query = User::query()
            ->whereHas('tradingAccounts', fn (Builder $query): Builder => $this->mt5OrTrialAccountScope($query))
            ->orderBy('id');

        $query->chunkById(100, function ($users) use (
            $dryRun,
            $limit,
            $mailer,
            $fromAddress,
            $fromName,
            $cc,
            &$processed,
            &$sent,
            &$failed,
            &$seenEmails,
        ): bool {
            foreach ($users as $user) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                $email = strtolower(trim((string) $user->email));

                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seenEmails[$email])) {
                    continue;
                }

                $seenEmails[$email] = true;
                $processed++;

                if ($dryRun) {
                    $this->line("DRY RUN: {$email}");

                    continue;
                }

                try {
                    Mail::to($email)
                        ->cc($cc)
                        ->send(new Mt5OnboardingSetupMail());

                    $sent++;
                    $this->info("Sent MT5 connector recovery email to {$email}; CC: {$cc}");

                    Log::info('Wolforix MT5 connector recovery email sent.', [
                        'email' => $email,
                        'cc' => $cc,
                        'mailer' => config('mail.automated_mailer'),
                        'from_address' => config('mail.automated_from.address'),
                        'template' => Mt5OnboardingSetupMail::class,
                        'connector_release' => config('wolforix.mt5_connector.release_label'),
                    ]);
                } catch (Throwable $exception) {
                    if ($mailer !== 'ceo') {
                        try {
                            $this->configureRecoverySender('ceo', 'ceo@wolforix.com', 'Wolforix CEO');

                            Mail::to($email)
                                ->cc($cc)
                                ->send(new Mt5OnboardingSetupMail());

                            $sent++;
                            $this->warn("Initial mailer failed; sent {$email} through CEO mailer.");

                            Log::warning('Wolforix MT5 connector recovery email sent through CEO fallback.', [
                                'email' => $email,
                                'cc' => $cc,
                                'initial_mailer' => $mailer,
                                'fallback_mailer' => 'ceo',
                                'initial_error' => $exception->getMessage(),
                                'template' => Mt5OnboardingSetupMail::class,
                            ]);

                            $this->configureRecoverySender($mailer, $fromAddress, $fromName);

                            continue;
                        } catch (Throwable $fallbackException) {
                            $exception = $fallbackException;
                            $this->configureRecoverySender($mailer, $fromAddress, $fromName);
                        }
                    }

                    $failed++;
                    $this->error("Failed to send MT5 connector recovery email to {$email}: {$exception->getMessage()}");

                    Log::error('Wolforix MT5 connector recovery email failed.', [
                        'email' => $email,
                        'cc' => $cc,
                        'mailer' => config('mail.automated_mailer'),
                        'from_address' => config('mail.automated_from.address'),
                        'error' => $exception->getMessage(),
                        'template' => Mt5OnboardingSetupMail::class,
                    ]);
                }
            }

            return true;
        });

        $summary = [
            'processed' => $processed,
            'sent' => $sent,
            'failed' => $failed,
            'dry_run' => $dryRun,
            'cc' => $cc,
            'mailer' => config('mail.automated_mailer'),
            'from_address' => config('mail.automated_from.address'),
            'connector_release' => config('wolforix.mt5_connector.release_label'),
        ];

        Log::info('Wolforix MT5 connector recovery email campaign completed.', $summary);
        $this->info(sprintf(
            'MT5 connector recovery email campaign complete. Processed: %d; Sent: %d; Failed: %d; Dry run: %s.',
            $processed,
            $sent,
            $failed,
            $dryRun ? 'yes' : 'no',
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function configureRecoverySender(string $mailer, string $fromAddress, string $fromName): void
    {
        config()->set('mail.automated_mailer', $mailer);
        config()->set('mail.automated_from.address', $fromAddress);
        config()->set('mail.automated_from.name', $fromName);
    }

    private function mt5OrTrialAccountScope(Builder $query): Builder
    {
        return $query
            ->where('is_trial', true)
            ->orWhere('platform_slug', 'mt5')
            ->orWhere('platform', 'MT5');
    }

    private function positiveIntegerOption(string $name): ?int
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        return max((int) $value, 0) ?: null;
    }
}
