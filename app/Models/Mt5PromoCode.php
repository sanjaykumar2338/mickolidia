<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mt5PromoCode extends Model
{
    protected $fillable = [
        'code',
        'mt5_account_pool_entry_id',
        'mt5_login',
        'used_at',
        'used_by_user_id',
        'used_order_id',
        'used_trading_account_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function poolEntry(): BelongsTo
    {
        return $this->belongsTo(Mt5AccountPoolEntry::class, 'mt5_account_pool_entry_id');
    }

    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    public function usedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'used_order_id');
    }

    public function usedTradingAccount(): BelongsTo
    {
        return $this->belongsTo(TradingAccount::class, 'used_trading_account_id');
    }

    public function getIsUsedAttribute(): bool
    {
        return $this->used_at !== null;
    }
}
