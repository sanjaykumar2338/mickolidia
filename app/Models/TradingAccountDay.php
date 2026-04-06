<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingAccountDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'trading_account_id',
        'phase_index',
        'trading_date',
        'activity_count',
        'volume',
        'first_activity_at',
        'last_activity_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'phase_index' => 'integer',
            'trading_date' => 'date',
            'activity_count' => 'integer',
            'volume' => 'decimal:2',
            'first_activity_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function tradingAccount(): BelongsTo
    {
        return $this->belongsTo(TradingAccount::class);
    }
}
