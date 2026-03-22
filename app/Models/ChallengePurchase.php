<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengePurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'challenge_plan_id',
        'challenge_type',
        'account_size',
        'currency',
        'account_status',
        'funded_status',
        'started_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'account_size' => 'integer',
            'started_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function challengePlan(): BelongsTo
    {
        return $this->belongsTo(ChallengePlan::class);
    }
}
