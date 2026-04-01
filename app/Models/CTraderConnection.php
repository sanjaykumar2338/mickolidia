<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CTraderConnection extends Model
{
    use HasFactory;

    protected $table = 'ctrader_connections';

    protected $fillable = [
        'user_id',
        'token_type',
        'scope',
        'access_token',
        'refresh_token',
        'expires_at',
        'last_refreshed_at',
        'last_authorized_at',
        'last_synced_accounts_at',
        'authorized_accounts',
        'meta',
        'last_error',
        'last_error_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
            'last_authorized_at' => 'datetime',
            'last_synced_accounts_at' => 'datetime',
            'authorized_accounts' => 'array',
            'meta' => 'array',
            'last_error_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tradingAccounts(): HasMany
    {
        return $this->hasMany(TradingAccount::class);
    }
}
