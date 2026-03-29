<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingAccountStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'trading_account_id',
        'previous_status',
        'new_status',
        'previous_phase_index',
        'new_phase_index',
        'source',
        'context',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_phase_index' => 'integer',
            'new_phase_index' => 'integer',
            'context' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    public function tradingAccount(): BelongsTo
    {
        return $this->belongsTo(TradingAccount::class);
    }
}
