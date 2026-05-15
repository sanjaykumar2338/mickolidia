<?php

namespace App\Console\Commands;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class ShowMt5Credentials extends Command
{
    private const ALLOWED_LOGIN = '335405';

    private const ALLOWED_ACCOUNT_REFERENCE = 'WFX-MT5-00057-8HN7';

    protected $signature = 'wolforix:show-mt5-credentials
        {login : MT5 login. This command only allows 335405}
        {--account-reference= : Wolforix account reference. This command only allows WFX-MT5-00057-8HN7}
        {--show-secret : Print decrypted passwords instead of masked values}';

    protected $description = 'Read-only reveal of MT5 credentials for the locked WFX-MT5-00057-8HN7 / 335405 diagnostic target.';

    public function handle(): int
    {
        $login = trim((string) $this->argument('login'));
        $accountReference = trim((string) $this->option('account-reference'));
        $showSecret = (bool) $this->option('show-secret');

        if ($login !== self::ALLOWED_LOGIN) {
            $this->error('Refusing to run: this diagnostic only allows MT5 login '.self::ALLOWED_LOGIN.'.');

            return self::FAILURE;
        }

        if ($accountReference !== self::ALLOWED_ACCOUNT_REFERENCE) {
            $this->error('Refusing to run: this diagnostic only allows account reference '.self::ALLOWED_ACCOUNT_REFERENCE.'.');

            return self::FAILURE;
        }

        /** @var Mt5AccountPoolEntry|null $poolEntry */
        $poolEntry = Mt5AccountPoolEntry::query()
            ->where('login', self::ALLOWED_LOGIN)
            ->first();

        if (! $poolEntry instanceof Mt5AccountPoolEntry) {
            $this->error('MT5 pool entry was not found for login '.self::ALLOWED_LOGIN.'.');

            return self::FAILURE;
        }

        /** @var TradingAccount|null $tradingAccount */
        $tradingAccount = TradingAccount::query()
            ->where('account_reference', self::ALLOWED_ACCOUNT_REFERENCE)
            ->first();

        if (! $tradingAccount instanceof TradingAccount) {
            $this->error('Trading account was not found for account reference '.self::ALLOWED_ACCOUNT_REFERENCE.'.');

            return self::FAILURE;
        }

        if (
            $poolEntry->allocated_trading_account_id !== null
            && (int) $poolEntry->allocated_trading_account_id !== (int) $tradingAccount->id
        ) {
            $this->error('Refusing to run: login '.self::ALLOWED_LOGIN.' is allocated to a different trading account.');

            return self::FAILURE;
        }

        $passwordResult = $this->decryptRawCredential($poolEntry, 'password');
        $investorPasswordResult = $this->decryptRawCredential($poolEntry, 'investor_password');

        if (! $passwordResult['ok'] || ! $investorPasswordResult['ok']) {
            $this->error('Credential decrypt failed with the current APP_KEY.');
            $this->table(['Field', 'State'], [
                ['password', $passwordResult['state']],
                ['investor_password', $investorPasswordResult['state']],
            ]);
            $this->warn('No credentials were printed.');

            return self::FAILURE;
        }

        if ($showSecret) {
            $this->warn('Sensitive credentials shown once, do not share publicly');
        } else {
            $this->warn('Secrets are masked. Re-run with --show-secret only when you are ready to view them.');
        }

        $this->table(['Field', 'Value'], [
            ['login', $poolEntry->login],
            ['server', (string) $poolEntry->server],
            ['password', $showSecret ? $passwordResult['value'] : $this->maskSecret($passwordResult['value'])],
            ['investor_password', $showSecret ? $investorPasswordResult['value'] : $this->maskSecret($investorPasswordResult['value'])],
            ['account_reference', (string) $tradingAccount->account_reference],
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array{ok: bool, value: string, state: string}
     */
    private function decryptRawCredential(Mt5AccountPoolEntry $poolEntry, string $field): array
    {
        $rawValue = (string) $poolEntry->getRawOriginal($field);

        if ($rawValue === '') {
            return [
                'ok' => false,
                'value' => '',
                'state' => 'missing',
            ];
        }

        try {
            $decrypted = Crypt::decryptString($rawValue);

            return [
                'ok' => $decrypted !== '',
                'value' => $decrypted,
                'state' => $decrypted !== '' ? 'decryptable_present' : 'empty_after_decrypt',
            ];
        } catch (DecryptException $exception) {
            return [
                'ok' => false,
                'value' => '',
                'state' => 'decrypt_failed: '.$exception->getMessage(),
            ];
        }
    }

    private function maskSecret(string $secret): string
    {
        $length = strlen($secret);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max($length - 4, 0)).substr($secret, -4);
    }
}
