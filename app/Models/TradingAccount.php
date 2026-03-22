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
        'account_type',
        'is_trial',
        'starting_balance',
        'balance',
        'equity',
        'daily_drawdown',
        'max_drawdown',
        'profit_loss',
        'total_profit',
        'today_profit',
        'drawdown_percent',
        'consistency_limit_percent',
        'minimum_trading_days',
        'trading_days_completed',
        'allowed_symbols',
        'trial_status',
        'trial_started_at',
        'last_activity_at',
        'ended_at',
        'milestone_popup_3_shown',
        'milestone_popup_5_shown',
        'encouragement_email_sent_at',
        'synced_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_trial' => 'boolean',
            'starting_balance' => 'decimal:2',
            'balance' => 'decimal:2',
            'equity' => 'decimal:2',
            'daily_drawdown' => 'decimal:2',
            'max_drawdown' => 'decimal:2',
            'profit_loss' => 'decimal:2',
            'total_profit' => 'decimal:2',
            'today_profit' => 'decimal:2',
            'drawdown_percent' => 'decimal:2',
            'consistency_limit_percent' => 'decimal:2',
            'allowed_symbols' => 'array',
            'trial_started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'ended_at' => 'datetime',
            'milestone_popup_3_shown' => 'boolean',
            'milestone_popup_5_shown' => 'boolean',
            'encouragement_email_sent_at' => 'datetime',
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
