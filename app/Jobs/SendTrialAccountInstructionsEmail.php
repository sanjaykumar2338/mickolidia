<?php

namespace App\Jobs;

use App\Mail\TrialAccountInstructionsMail;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTrialAccountInstructionsEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public int $tradingAccountId,
    ) {
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        $trialAccount = TradingAccount::query()->find($this->tradingAccountId);

        if (! $user instanceof User || ! $trialAccount instanceof TradingAccount) {
            return;
        }

        if (filled(data_get($trialAccount->meta, 'trial_instructions_email_sent_at'))) {
            Log::info('trial.instructions_email_skipped_already_sent', [
                'user_id' => $user->id,
                'trading_account_id' => $trialAccount->id,
            ]);

            return;
        }

        $startedAt = microtime(true);

        Mail::to($user->email)->send(new TrialAccountInstructionsMail($user));

        $meta = is_array($trialAccount->meta) ? $trialAccount->meta : [];
        $meta['trial_instructions_email_sent_at'] = now()->toIso8601String();

        $trialAccount->forceFill(['meta' => $meta])->save();

        Log::info('trial.instructions_email_sent', [
            'user_id' => $user->id,
            'trading_account_id' => $trialAccount->id,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
