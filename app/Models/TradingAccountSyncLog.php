<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingAccountSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'trading_account_id',
        'platform',
        'status',
        'message',
        'error_message',
        'started_at',
        'completed_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function tradingAccount(): BelongsTo
    {
        return $this->belongsTo(TradingAccount::class);
    }
}
