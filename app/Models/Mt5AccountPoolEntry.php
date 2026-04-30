<?php

namespace App\Models;

use Database\Factories\Mt5AccountPoolEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mt5AccountPoolEntry extends Model
{
    /** @use HasFactory<Mt5AccountPoolEntryFactory> */
    use HasFactory;

    public const SOURCE_POOL_CLIENT = 'client_pool';

    public const SOURCE_POOL_INTERNAL = 'internal_only';

    public const BROKER_FUSION_MARKETS = 'FusionMarkets';

    public const PLATFORM_MT5 = 'MT5';

    protected $fillable = [
        'login',
        'password',
        'investor_password',
        'server',
        'account_size',
        'currency_code',
        'source_status',
        'source_file',
        'source_batch',
        'source_pool',
        'source_created_at',
        'allocated_trading_account_id',
        'allocated_user_id',
        'allocated_at',
        'is_promo',
        'is_available',
        'meta',
    ];

    protected static function newFactory(): Mt5AccountPoolEntryFactory
    {
        return Mt5AccountPoolEntryFactory::new();
    }

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'investor_password' => 'encrypted',
            'account_size' => 'integer',
            'source_created_at' => 'date',
            'allocated_at' => 'datetime',
            'is_promo' => 'boolean',
            'is_available' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function allocatedTradingAccount(): BelongsTo
    {
        return $this->belongsTo(TradingAccount::class, 'allocated_trading_account_id');
    }

    public function allocatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_user_id');
    }

    public function promoCode()
    {
        return $this->hasOne(Mt5PromoCode::class, 'mt5_account_pool_entry_id');
    }
}
