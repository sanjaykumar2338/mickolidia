<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class TradingAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'challenge_plan_id',
        'account_reference',
        'platform',
        'stage',
        'status',
        'starting_balance',
        'balance',
        'total_profit',
        'today_profit',
        'drawdown_percent',
        'consistency_limit_percent',
        'minimum_trading_days',
        'trading_days_completed',
        'synced_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'starting_balance' => 'decimal:2',
            'balance' => 'decimal:2',
            'total_profit' => 'decimal:2',
            'today_profit' => 'decimal:2',
            'drawdown_percent' => 'decimal:2',
            'consistency_limit_percent' => 'decimal:2',
            'synced_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function challengePlan(): BelongsTo
    {
        return $this->belongsTo(ChallengePlan::class);
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }
}
