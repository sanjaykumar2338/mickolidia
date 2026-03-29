<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingAccountBalanceSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'trading_account_id',
        'snapshot_at',
        'balance',
        'equity',
        'profit_loss',
        'total_profit',
        'today_profit',
        'daily_drawdown',
        'max_drawdown',
        'drawdown_percent',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_at' => 'datetime',
            'balance' => 'decimal:2',
            'equity' => 'decimal:2',
            'profit_loss' => 'decimal:2',
            'total_profit' => 'decimal:2',
            'today_profit' => 'decimal:2',
            'daily_drawdown' => 'decimal:2',
            'max_drawdown' => 'decimal:2',
            'drawdown_percent' => 'decimal:2',
            'payload' => 'array',
        ];
    }

    public function tradingAccount(): BelongsTo
    {
        return $this->belongsTo(TradingAccount::class);
    }
}
