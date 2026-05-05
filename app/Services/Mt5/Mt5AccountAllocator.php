<?php

namespace App\Services\Mt5;

use App\Models\Mt5AccountPoolEntry;
use App\Models\Mt5PromoCode;
use App\Models\TradingAccount;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class Mt5AccountAllocator
{
    public function allocate(TradingAccount $account): ?Mt5AccountPoolEntry
    {
        $existingAllocation = Mt5AccountPoolEntry::query()
            ->where('allocated_trading_account_id', $account->id)
            ->lockForUpdate()
            ->first();

        if ($existingAllocation instanceof Mt5AccountPoolEntry) {
            try {
                $this->hydrateAccount($account, $existingAllocation, $this->credentialsFor($existingAllocation));
            } catch (DecryptException $exception) {
                $this->reportInvalidCredentials($existingAllocation, $exception);
            }

            return $existingAllocation;
        }

        if ($this->hasManualCredentials($account)) {
            return null;
        }

        $entries = Mt5AccountPoolEntry::query()
            ->where('source_pool', Mt5AccountPoolEntry::SOURCE_POOL_CLIENT)
            ->where('source_file', basename((string) config('wolforix.mt5_account_pool.fusionmarkets.source', 'public/Account List FusionMarkets-Demo30.04.ods')))
            ->where('meta->broker', (string) config('wolforix.mt5_account_pool.active_broker', Mt5AccountPoolEntry::BROKER_FUSION_MARKETS))
            ->where('meta->platform', (string) config('wolforix.mt5_account_pool.active_platform', Mt5AccountPoolEntry::PLATFORM_MT5))
            ->where('is_available', true)
            ->where('is_promo', false)
            ->whereNull('allocated_at')
            ->whereNull('allocated_trading_account_id')
            ->where('account_size', (int) $account->account_size)
            ->orderBy('source_created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($entries as $entry) {
            try {
                $credentials = $this->credentialsFor($entry);
            } catch (DecryptException $exception) {
                $this->reportInvalidCredentials($entry, $exception);

                continue;
            }

            $entry->forceFill([
                'allocated_trading_account_id' => $account->id,
                'allocated_user_id' => $account->user_id,
                'allocated_at' => now(),
                'is_available' => false,
            ])->save();

            $this->hydrateAccount($account, $entry, $credentials);

            return $entry;
        }

        return null;
    }

    public function allocatePromo(TradingAccount $account, Mt5PromoCode $promoCode): Mt5AccountPoolEntry
    {
        /** @var Mt5PromoCode $lockedPromoCode */
        $lockedPromoCode = Mt5PromoCode::query()
            ->with('poolEntry')
            ->lockForUpdate()
            ->findOrFail($promoCode->id);

        if ($lockedPromoCode->used_at !== null) {
            throw new RuntimeException('This promo code has already been used.');
        }

        $entry = $lockedPromoCode->poolEntry;

        if (! $entry instanceof Mt5AccountPoolEntry || ! $entry->is_promo) {
            throw new RuntimeException('This promo code is not linked to a promo MT5 account.');
        }

        if ($entry->allocated_at !== null || $entry->allocated_trading_account_id !== null) {
            throw new RuntimeException('The linked promo MT5 account is already allocated.');
        }

        if ((int) $entry->account_size !== (int) $account->account_size) {
            throw new RuntimeException('This promo code is linked to a different account size.');
        }

        $credentials = $this->credentialsFor($entry);

        $entry->forceFill([
            'allocated_trading_account_id' => $account->id,
            'allocated_user_id' => $account->user_id,
            'allocated_at' => now(),
            'is_available' => false,
        ])->save();

        $this->hydrateAccount($account, $entry, $credentials);

        $lockedPromoCode->forceFill([
            'used_at' => now(),
            'used_by_user_id' => $account->user_id,
            'used_order_id' => $account->order_id,
            'used_trading_account_id' => $account->id,
        ])->save();

        return $entry;
    }

    /**
     * @param  array{password: string|null, investor_password: string|null}  $entryCredentials
     */
    private function hydrateAccount(TradingAccount $account, Mt5AccountPoolEntry $entry, array $entryCredentials): void
    {
        $meta = is_array($account->meta) ? $account->meta : [];
        $credentials = is_array(Arr::get($meta, 'credentials')) ? Arr::get($meta, 'credentials') : [];

        $credentials['server'] = $entry->server;
        $credentials['mt5_server'] = $entry->server;
        $credentials['password'] = $entryCredentials['password'];
        $credentials['trading_password'] = $entryCredentials['password'];

        if (filled($entryCredentials['investor_password'])) {
            $credentials['investor_password'] = $entryCredentials['investor_password'];
            $credentials['readonly_password'] = $entryCredentials['investor_password'];
        }

        $credentials['last_updated_at'] = now()->toIso8601String();

        $meta['credentials'] = $credentials;
        $meta['mt5_server'] = $entry->server;
        $meta['broker'] = data_get($entry->meta, 'broker', Mt5AccountPoolEntry::BROKER_FUSION_MARKETS);
        $meta['provider'] = data_get($entry->meta, 'provider', Mt5AccountPoolEntry::BROKER_FUSION_MARKETS);
        $meta['platform'] = data_get($entry->meta, 'platform', Mt5AccountPoolEntry::PLATFORM_MT5);
        $meta['mt5_sync'] = array_filter([
            'identifier' => $entry->login,
            'account_reference' => $account->account_reference,
            'server' => $entry->server,
            'broker' => data_get($entry->meta, 'broker', Mt5AccountPoolEntry::BROKER_FUSION_MARKETS),
            'status' => $account->last_synced_at ? 'connected' : 'waiting_for_first_sync',
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        $meta['mt5_pool_entry'] = array_filter([
            'id' => $entry->id,
            'source_pool' => $entry->source_pool,
            'source_file' => $entry->source_file,
            'source_batch' => $entry->source_batch,
            'source_status' => $entry->source_status,
            'broker' => data_get($entry->meta, 'broker'),
            'platform' => data_get($entry->meta, 'platform'),
            'source_created_at' => optional($entry->source_created_at)->toDateString(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $account->forceFill([
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => $entry->login,
            'platform_account_id' => $entry->login,
            'platform_environment' => $entry->server,
            'platform_status' => $account->last_synced_at ? 'connected' : 'waiting_for_first_sync',
            'meta' => $meta,
        ])->save();
    }

    /**
     * @return array{password: string|null, investor_password: string|null}
     */
    private function credentialsFor(Mt5AccountPoolEntry $entry): array
    {
        return [
            'password' => $this->credentialValue($entry, 'password'),
            'investor_password' => $this->credentialValue($entry, 'investor_password'),
        ];
    }

    private function credentialValue(Mt5AccountPoolEntry $entry, string $key): ?string
    {
        try {
            $value = $entry->{$key};

            return filled($value) ? (string) $value : null;
        } catch (DecryptException $exception) {
            $rawValue = $entry->getRawOriginal($key);

            if (! filled($rawValue)) {
                return null;
            }

            if (! $entry->is_promo || $this->looksLikeEncryptedCastPayload((string) $rawValue)) {
                throw $exception;
            }

            Log::notice('MT5 account pool entry is using legacy plaintext credentials; assignment continued.', [
                'mt5_account_pool_entry_id' => $entry->id,
                'login' => $entry->login,
                'source_pool' => $entry->source_pool,
                'source_file' => $entry->source_file,
                'credential' => $key,
            ]);

            return (string) $rawValue;
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

    private function reportInvalidCredentials(Mt5AccountPoolEntry $entry, DecryptException $exception): void
    {
        Log::warning('MT5 account pool entry credentials could not be decrypted.', [
            'mt5_account_pool_entry_id' => $entry->id,
            'login' => $entry->login,
            'source_pool' => $entry->source_pool,
            'source_file' => $entry->source_file,
            'account_size' => $entry->account_size,
            'exception' => $exception::class,
        ]);
    }

    private function hasManualCredentials(TradingAccount $account): bool
    {
        $meta = is_array($account->meta) ? $account->meta : [];
        $credentials = is_array(Arr::get($meta, 'credentials')) ? Arr::get($meta, 'credentials') : [];

        return filled($account->platform_login)
            || filled($account->platform_account_id)
            || filled(Arr::get($credentials, 'server'))
            || filled(Arr::get($credentials, 'password'))
            || filled(Arr::get($credentials, 'trading_password'))
            || filled(Arr::get($credentials, 'investor_password'))
            || filled(Arr::get($credentials, 'readonly_password'));
    }
}
