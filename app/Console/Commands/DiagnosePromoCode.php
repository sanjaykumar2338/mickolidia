<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\Mt5PromoCode;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DiagnosePromoCode extends Command
{
    protected $signature = 'wolforix:diagnose-promo
        {code : Promo code to inspect}
        {--email= : Client email to cross-check}
        {--log-lines=500 : Maximum matching log lines to print}';

    protected $description = 'Read-only diagnosis for MT5 giveaway promo code assignment failures.';

    public function handle(): int
    {
        $code = trim((string) $this->argument('code'));
        $email = trim((string) $this->option('email'));
        $normalizedCode = $this->normalizeGiveawayPromoCode($code);

        $this->info('Read-only promo diagnosis');
        $this->line('Connection: '.config('database.default').' / database: '.(DB::connection()->getDatabaseName() ?: '(unknown)'));
        $this->newLine();

        try {
            $promoCode = Mt5PromoCode::query()
                ->with(['poolEntry', 'usedByUser', 'usedOrder', 'usedTradingAccount'])
                ->whereRaw("LOWER(REPLACE(REPLACE(code, '-', ''), ' ', '')) = ?", [$normalizedCode])
                ->first();
        } catch (QueryException $exception) {
            $databaseMessage = $exception->getPrevious()?->getMessage() ?: $exception->getMessage();

            $this->error('Database read failed: '.$databaseMessage);
            $this->warn('No data was changed. Run this command where the application can read the target database.');

            return self::FAILURE;
        }

        if (! $promoCode instanceof Mt5PromoCode) {
            $this->error('Promo code was not found.');
            $this->printLogMatches($code, $email, [], (int) $this->option('log-lines'));

            return self::FAILURE;
        }

        $poolEntry = $promoCode->poolEntry;
        $expectedChallengeType = 'two_step';
        $expectedAccountSize = 10000;

        $this->table(['Promo field', 'Value'], [
            ['id', (string) $promoCode->id],
            ['code', $promoCode->code],
            ['mt5_login', (string) $promoCode->mt5_login],
            ['pool_entry_id', (string) $promoCode->mt5_account_pool_entry_id],
            ['used_at', $this->formatValue($promoCode->used_at)],
            ['used_by_user', $promoCode->usedByUser ? $promoCode->usedByUser->id.' / '.$promoCode->usedByUser->email : '-'],
            ['used_order', $promoCode->usedOrder ? $promoCode->usedOrder->id.' / '.$promoCode->usedOrder->order_number : '-'],
            ['used_trading_account', $promoCode->usedTradingAccount ? (string) $promoCode->usedTradingAccount->id : '-'],
            ['expected_flow', '$10K 2-step giveaway'],
        ]);

        if ($poolEntry instanceof Mt5AccountPoolEntry) {
            $credentialState = $this->credentialState($poolEntry);

            $this->table(['Pool entry field', 'Value'], [
                ['id', (string) $poolEntry->id],
                ['login', $poolEntry->login],
                ['server', $poolEntry->server],
                ['account_size', (string) $poolEntry->account_size],
                ['source_status', (string) $poolEntry->source_status],
                ['source_pool', (string) $poolEntry->source_pool],
                ['source_file', (string) $poolEntry->source_file],
                ['is_promo', $this->yesNo((bool) $poolEntry->is_promo)],
                ['is_available', $this->yesNo((bool) $poolEntry->is_available)],
                ['allocated_at', $this->formatValue($poolEntry->allocated_at)],
                ['allocated_user_id', $this->formatValue($poolEntry->allocated_user_id)],
                ['allocated_trading_account_id', $this->formatValue($poolEntry->allocated_trading_account_id)],
                ['password_state', $credentialState['password']],
                ['investor_password_state', $credentialState['investor_password']],
                ['meta.broker', (string) data_get($poolEntry->meta, 'broker', '-')],
                ['meta.platform', (string) data_get($poolEntry->meta, 'platform', '-')],
                ['meta.promo_marker', (string) data_get($poolEntry->meta, 'promo_marker', '-')],
            ]);
        } else {
            $this->error('Promo code is not linked to an MT5 account pool entry.');
        }

        $this->printClientRows($email, $promoCode);
        $this->printPoolCounts($poolEntry?->account_size ?? $expectedAccountSize);
        $this->printDecision($promoCode, $poolEntry, $expectedChallengeType, $expectedAccountSize);

        $terms = array_filter([
            $promoCode->code,
            $promoCode->mt5_login,
            $email,
            (string) $promoCode->id,
            $promoCode->usedOrder?->order_number,
        ], static fn (?string $value): bool => filled($value));

        $this->printLogMatches($code, $email, $terms, (int) $this->option('log-lines'));

        return self::SUCCESS;
    }

    private function printClientRows(string $email, Mt5PromoCode $promoCode): void
    {
        if ($email === '') {
            return;
        }

        $users = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->latest('id')
            ->limit(10)
            ->get(['id', 'name', 'email', 'created_at', 'updated_at']);

        $this->newLine();
        $this->info('Users matching email');
        $this->table(['id', 'name', 'email', 'created_at'], $users->map(fn (User $user): array => [
            (string) $user->id,
            (string) $user->name,
            (string) $user->email,
            $this->formatValue($user->created_at),
        ])->all());

        $userIds = $users->pluck('id')->all();

        $orders = Order::query()
            ->where(function ($query) use ($email, $userIds, $promoCode): void {
                $query->whereRaw('LOWER(email) = ?', [Str::lower($email)])
                    ->orWhere('metadata->mt5_giveaway_promo->code', $promoCode->code);

                if ($userIds !== []) {
                    $query->orWhereIn('user_id', $userIds);
                }
            })
            ->latest('id')
            ->limit(15)
            ->get();

        $this->newLine();
        $this->info('Related orders');
        $this->table(['id', 'order_number', 'user_id', 'email', 'name', 'type', 'size', 'provider', 'payment', 'status', 'promo_code', 'created_at'], $orders->map(fn (Order $order): array => [
            (string) $order->id,
            (string) $order->order_number,
            $this->formatValue($order->user_id),
            (string) $order->email,
            (string) $order->full_name,
            (string) $order->challenge_type,
            (string) $order->account_size,
            (string) $order->payment_provider,
            (string) $order->payment_status,
            (string) $order->order_status,
            (string) data_get($order->metadata, 'mt5_giveaway_promo.code', '-'),
            $this->formatValue($order->created_at),
        ])->all());

        $orderIds = $orders->pluck('id')->all();

        $accounts = TradingAccount::query()
            ->where(function ($query) use ($userIds, $orderIds, $promoCode): void {
                if ($userIds !== []) {
                    $query->orWhereIn('user_id', $userIds);
                }

                if ($orderIds !== []) {
                    $query->orWhereIn('order_id', $orderIds);
                }

                $query->orWhere('platform_login', $promoCode->mt5_login)
                    ->orWhere('platform_account_id', $promoCode->mt5_login);
            })
            ->latest('id')
            ->limit(15)
            ->get();

        $this->newLine();
        $this->info('Related trading accounts');
        $this->table(['id', 'user_id', 'order_id', 'challenge_type', 'size', 'status', 'account_status', 'platform_login', 'created_at'], $accounts->map(fn (TradingAccount $account): array => [
            (string) $account->id,
            $this->formatValue($account->user_id),
            $this->formatValue($account->order_id),
            (string) $account->challenge_type,
            (string) $account->account_size,
            (string) $account->status,
            (string) $account->account_status,
            (string) ($account->platform_login ?: '-'),
            $this->formatValue($account->created_at),
        ])->all());
    }

    private function printPoolCounts(int $accountSize): void
    {
        $counts = [
            ['promo linked/free for size', Mt5AccountPoolEntry::query()
                ->where('is_promo', true)
                ->where('account_size', $accountSize)
                ->whereNull('allocated_at')
                ->whereNull('allocated_trading_account_id')
                ->count()],
            ['promo allocated for size', Mt5AccountPoolEntry::query()
                ->where('is_promo', true)
                ->where('account_size', $accountSize)
                ->where(function ($query): void {
                    $query->whereNotNull('allocated_at')
                        ->orWhereNotNull('allocated_trading_account_id');
                })
                ->count()],
            ['normal available for size', Mt5AccountPoolEntry::query()
                ->where('is_promo', false)
                ->where('is_available', true)
                ->where('account_size', $accountSize)
                ->whereNull('allocated_at')
                ->whereNull('allocated_trading_account_id')
                ->count()],
        ];

        $this->newLine();
        $this->info('Pool availability counts');
        $this->table(['bucket', 'count'], array_map(static fn (array $row): array => [$row[0], (string) $row[1]], $counts));
    }

    private function printDecision(Mt5PromoCode $promoCode, ?Mt5AccountPoolEntry $poolEntry, string $expectedChallengeType, int $expectedAccountSize): void
    {
        $reasons = [];

        if ($promoCode->used_at !== null) {
            $reasons[] = 'Promo code already used.';
        }

        if (! $poolEntry instanceof Mt5AccountPoolEntry) {
            $reasons[] = 'Promo code has no linked pool entry.';
        } else {
            if (! $poolEntry->is_promo) {
                $reasons[] = 'Linked pool entry is not marked as promo.';
            }

            if ((int) $poolEntry->account_size !== $expectedAccountSize) {
                $reasons[] = 'Linked pool entry account size does not match $10K giveaway flow.';
            }

            if ($poolEntry->allocated_at !== null || $poolEntry->allocated_trading_account_id !== null) {
                $reasons[] = 'Linked pool entry is already allocated.';
            }

            $credentials = $this->credentialState($poolEntry);

            foreach ($credentials as $field => $state) {
                if (Str::startsWith($state, 'decrypt_failed')) {
                    $reasons[] = $field.' cannot be decrypted with the current APP_KEY.';
                }
            }
        }

        $this->newLine();
        $this->info('Diagnostic decision');

        if ($reasons === []) {
            $this->line('No blocking row-state issue found for '.$expectedChallengeType.' / '.$expectedAccountSize.'. If checkout still fails, inspect runtime logs around order fulfillment.');

            return;
        }

        foreach ($reasons as $reason) {
            $this->warn($reason);
        }
    }

    /**
     * @return array{password: string, investor_password: string}
     */
    private function credentialState(Mt5AccountPoolEntry $entry): array
    {
        return [
            'password' => $this->credentialFieldState($entry, 'password'),
            'investor_password' => $this->credentialFieldState($entry, 'investor_password'),
        ];
    }

    private function credentialFieldState(Mt5AccountPoolEntry $entry, string $field): string
    {
        try {
            $value = $entry->{$field};

            return filled($value) ? 'decryptable_present' : 'empty';
        } catch (DecryptException) {
            $rawValue = (string) $entry->getRawOriginal($field);

            if (! filled($rawValue)) {
                return 'empty';
            }

            if ($entry->is_promo && ! $this->looksLikeEncryptedCastPayload($rawValue)) {
                return 'legacy_plaintext_present';
            }

            return 'decrypt_failed';
        }
    }

    private function looksLikeEncryptedCastPayload(string $value): bool
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && array_key_exists('iv', $payload)
            && array_key_exists('value', $payload)
            && array_key_exists('mac', $payload);
    }

    /**
     * @param  list<string>  $terms
     */
    private function printLogMatches(string $code, string $email, array $terms, int $limit): void
    {
        $logFiles = collect(File::glob(storage_path('logs/*.log')) ?: [])
            ->sortDesc()
            ->values();

        $terms = collect(array_merge($terms, [
            $code,
            $email,
            'checkout.giveaway_promo_fulfillment_failed',
            'MT5 account pool entry credentials could not be decrypted',
        ]))
            ->filter(fn (?string $term): bool => filled($term))
            ->map(fn (string $term): string => Str::lower($term))
            ->unique()
            ->values()
            ->all();

        $matches = [];

        foreach ($logFiles as $logFile) {
            $handle = fopen((string) $logFile, 'rb');

            if ($handle === false) {
                continue;
            }

            $lineNumber = 0;

            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $lowerLine = Str::lower($line);

                foreach ($terms as $term) {
                    if (str_contains($lowerLine, $term)) {
                        $matches[] = [
                            basename((string) $logFile).':'.$lineNumber,
                            trim($line),
                        ];

                        break;
                    }
                }
            }

            fclose($handle);
        }

        $this->newLine();
        $this->info('Local log matches');

        if ($matches === []) {
            $this->line('No matching local log lines found.');

            return;
        }

        $this->table(['file:line', 'line'], array_slice($matches, max(count($matches) - $limit, 0)));
    }

    private function normalizeGiveawayPromoCode(string $promoCode): string
    {
        return strtolower((string) preg_replace('/[\s\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}\p{Pd}]+/u', '', trim($promoCode)));
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

        return (string) $value;
    }
}
