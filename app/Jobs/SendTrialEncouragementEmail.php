<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTrialEncouragementEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tradingAccountId,
        public string $email,
    ) {
    }

    public function handle(): void
    {
        Log::info('Trial encouragement email placeholder triggered.', [
            'trading_account_id' => $this->tradingAccountId,
            'email' => $this->email,
            'message' => 'Keep going and improve your performance.',
        ]);
    }
}
