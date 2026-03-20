<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ChallengePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'account_size',
        'currency',
        'entry_fee',
        'profit_target',
        'daily_loss_limit',
        'max_loss_limit',
        'steps',
        'profit_share',
        'first_payout_days',
        'minimum_trading_days',
        'payout_cycle_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'account_size' => 'integer',
            'entry_fee' => 'decimal:2',
            'profit_target' => 'decimal:2',
            'daily_loss_limit' => 'decimal:2',
            'max_loss_limit' => 'decimal:2',
            'profit_share' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function tradingAccounts(): HasMany
    {
        return $this->hasMany(TradingAccount::class);
    }
}
