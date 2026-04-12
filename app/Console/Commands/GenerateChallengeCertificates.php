<?php

namespace App\Console\Commands;

use App\Models\TradingAccount;
use App\Services\Challenge\ChallengeCertificateGenerator;
use Illuminate\Console\Command;

class GenerateChallengeCertificates extends Command
{
    protected $signature = 'certificates:generate
        {--account= : Generate a certificate for one trading account ID}
        {--force : Regenerate even when a certificate file already exists}';

    protected $description = 'Generate Wolforix funded trader certificates for passed challenge accounts.';

    public function handle(ChallengeCertificateGenerator $certificateGenerator): int
    {
        $accountId = $this->option('account');
        $force = (bool) $this->option('force');
        $query = TradingAccount::query()
            ->with('user')
            ->where('is_trial', false)
            ->where('challenge_status', 'passed')
            ->when($accountId, fn ($builder) => $builder->whereKey((int) $accountId))
            ->orderBy('id');

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->info('No passed challenge accounts matched the certificate generation criteria.');

            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            $certificate = $certificateGenerator->ensureForAccount($account, $force);

            if ($certificate === null) {
                $this->warn("Skipped account #{$account->id}; certificate was not generated.");

                continue;
            }

            $this->line("Generated certificate for account #{$account->id}: {$certificate['path']}");
        }

        return self::SUCCESS;
    }
}
