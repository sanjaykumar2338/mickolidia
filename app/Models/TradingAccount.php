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
        'ctrader_connection_id',
        'challenge_plan_id',
        'order_id',
        'challenge_purchase_id',
        'challenge_type',
        'account_size',
        'account_reference',
        'platform',
        'platform_slug',
        'platform_account_id',
        'platform_login',
        'platform_environment',
        'platform_status',
        'stage',
        'status',
        'account_type',
        'account_phase',
        'phase_index',
        'account_status',
        'challenge_status',
        'is_funded',
        'is_trial',
        'activated_at',
        'phase_started_at',
        'passed_at',
        'failed_at',
        'failure_reason',
        'failure_context',
        'starting_balance',
        'phase_starting_balance',
        'phase_reference_balance',
        'balance',
        'equity',
        'highest_equity_today',
        'daily_drawdown',
        'daily_loss_used',
        'max_drawdown',
        'max_drawdown_used',
        'profit_loss',
        'total_profit',
        'today_profit',
        'drawdown_percent',
        'profit_target_percent',
        'profit_target_amount',
        'profit_target_progress_percent',
        'daily_drawdown_limit_percent',
        'daily_drawdown_limit_amount',
        'max_drawdown_limit_percent',
        'max_drawdown_limit_amount',
        'profit_split',
        'consistency_limit_percent',
        'minimum_trading_days',
        'trading_days_completed',
        'allowed_symbols',
        'trial_status',
        'trial_started_at',
        'last_activity_at',
        'ended_at',
        'payout_eligible_at',
        'first_payout_eligible_at',
        'payout_cycle_started_at',
        'last_balance_change_at',
        'milestone_popup_3_shown',
        'milestone_popup_5_shown',
        'encouragement_email_sent_at',
        'synced_at',
        'sync_status',
        'sync_source',
        'server_day',
        'last_synced_at',
        'last_sync_started_at',
        'last_sync_completed_at',
        'last_evaluated_at',
        'sync_error',
        'sync_error_at',
        'rule_state',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'account_size' => 'integer',
            'phase_index' => 'integer',
            'is_funded' => 'boolean',
            'is_trial' => 'boolean',
            'starting_balance' => 'decimal:2',
            'phase_starting_balance' => 'decimal:2',
            'phase_reference_balance' => 'decimal:2',
            'balance' => 'decimal:2',
            'equity' => 'decimal:2',
            'highest_equity_today' => 'decimal:2',
            'daily_drawdown' => 'decimal:2',
            'daily_loss_used' => 'decimal:2',
            'max_drawdown' => 'decimal:2',
            'max_drawdown_used' => 'decimal:2',
            'profit_loss' => 'decimal:2',
            'total_profit' => 'decimal:2',
            'today_profit' => 'decimal:2',
            'drawdown_percent' => 'decimal:2',
            'profit_target_percent' => 'decimal:2',
            'profit_target_amount' => 'decimal:2',
            'profit_target_progress_percent' => 'decimal:2',
            'daily_drawdown_limit_percent' => 'decimal:2',
            'daily_drawdown_limit_amount' => 'decimal:2',
            'max_drawdown_limit_percent' => 'decimal:2',
            'max_drawdown_limit_amount' => 'decimal:2',
            'profit_split' => 'decimal:2',
            'consistency_limit_percent' => 'decimal:2',
            'allowed_symbols' => 'array',
            'activated_at' => 'datetime',
            'phase_started_at' => 'datetime',
            'passed_at' => 'datetime',
            'failed_at' => 'datetime',
            'failure_context' => 'array',
            'trial_started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'ended_at' => 'datetime',
            'payout_eligible_at' => 'datetime',
            'first_payout_eligible_at' => 'datetime',
            'payout_cycle_started_at' => 'datetime',
            'last_balance_change_at' => 'datetime',
            'milestone_popup_3_shown' => 'boolean',
            'milestone_popup_5_shown' => 'boolean',
            'encouragement_email_sent_at' => 'datetime',
            'synced_at' => 'datetime',
            'server_day' => 'date',
            'last_synced_at' => 'datetime',
            'last_sync_started_at' => 'datetime',
            'last_sync_completed_at' => 'datetime',
            'last_evaluated_at' => 'datetime',
            'sync_error_at' => 'datetime',
            'rule_state' => 'array',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ctraderConnection(): BelongsTo
    {
        return $this->belongsTo(CTraderConnection::class);
    }

    public function challengePlan(): BelongsTo
    {
        return $this->belongsTo(ChallengePlan::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function challengePurchase(): BelongsTo
    {
        return $this->belongsTo(ChallengePurchase::class);
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TradingAccountStatusHistory::class);
    }

    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(TradingAccountBalanceSnapshot::class);
    }

    public function tradingDays(): HasMany
    {
        return $this->hasMany(TradingAccountDay::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(TradingAccountSyncLog::class);
    }
}
