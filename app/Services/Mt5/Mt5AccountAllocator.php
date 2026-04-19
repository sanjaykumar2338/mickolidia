<?php

namespace App\Services\Mt5;

use App\Models\Mt5AccountPoolEntry;
use App\Models\TradingAccount;
use Illuminate\Support\Arr;

class Mt5AccountAllocator
{
    public function allocate(TradingAccount $account): ?Mt5AccountPoolEntry
    {
        $existingAllocation = Mt5AccountPoolEntry::query()
            ->where('allocated_trading_account_id', $account->id)
            ->lockForUpdate()
            ->first();

        if ($existingAllocation instanceof Mt5AccountPoolEntry) {
            $this->hydrateAccount($account, $existingAllocation);

            return $existingAllocation;
        }

        if ($this->hasManualCredentials($account)) {
            return null;
        }

        /** @var Mt5AccountPoolEntry|null $entry */
        $entry = Mt5AccountPoolEntry::query()
            ->where('source_pool', Mt5AccountPoolEntry::SOURCE_POOL_CLIENT)
            ->where('is_available', true)
            ->whereNull('allocated_at')
            ->whereNull('allocated_trading_account_id')
            ->where('account_size', (int) $account->account_size)
            ->orderBy('source_created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (! $entry instanceof Mt5AccountPoolEntry) {
            return null;
        }

        $entry->forceFill([
            'allocated_trading_account_id' => $account->id,
            'allocated_user_id' => $account->user_id,
            'allocated_at' => now(),
            'is_available' => false,
        ])->save();

        $this->hydrateAccount($account, $entry);

        return $entry;
    }

    private function hydrateAccount(TradingAccount $account, Mt5AccountPoolEntry $entry): void
    {
        $meta = is_array($account->meta) ? $account->meta : [];
        $credentials = is_array(Arr::get($meta, 'credentials')) ? Arr::get($meta, 'credentials') : [];

        $credentials['server'] = $entry->server;
        $credentials['mt5_server'] = $entry->server;
        $credentials['password'] = $entry->password;
        $credentials['trading_password'] = $entry->password;
        $credentials['last_updated_at'] = now()->toIso8601String();

        $meta['credentials'] = $credentials;
        $meta['mt5_server'] = $entry->server;
        $meta['mt5_pool_entry'] = array_filter([
            'id' => $entry->id,
            'source_pool' => $entry->source_pool,
            'source_file' => $entry->source_file,
            'source_batch' => $entry->source_batch,
            'source_status' => $entry->source_status,
            'source_created_at' => optional($entry->source_created_at)->toDateString(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $account->forceFill([
            'platform' => 'MT5',
            'platform_slug' => 'mt5',
            'platform_login' => $entry->login,
            'platform_account_id' => $entry->login,
            'meta' => $meta,
        ])->save();
    }

    private function hasManualCredentials(TradingAccount $account): bool
    {
        $meta = is_array($account->meta) ? $account->meta : [];
        $credentials = is_array(Arr::get($meta, 'credentials')) ? Arr::get($meta, 'credentials') : [];

        return filled($account->platform_login)
            || filled($account->platform_account_id)
            || filled(Arr::get($credentials, 'server'))
            || filled(Arr::get($credentials, 'password'))
            || filled(Arr::get($credentials, 'trading_password'));
    }
}
