<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_CANCELED = 'canceled';

    public const STATUS_CREATED = 'created';
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'order_number',
        'user_id',
        'challenge_plan_id',
        'email',
        'full_name',
        'street_address',
        'city',
        'postal_code',
        'country',
        'challenge_type',
        'account_size',
        'currency',
        'payment_provider',
        'base_price',
        'discount_percent',
        'discount_amount',
        'final_price',
        'payment_status',
        'order_status',
        'external_checkout_id',
        'external_payment_id',
        'external_customer_id',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (! $order->order_number) {
                $order->order_number = 'WFX-ORD-'.Str::upper((string) Str::ulid());
            }
        });
    }

    protected function casts(): array
    {
        return [
            'account_size' => 'integer',
            'base_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_price' => 'decimal:2',
            'metadata' => 'array',
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

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function challengePurchase(): HasOne
    {
        return $this->hasOne(ChallengePurchase::class);
    }

    public function tradingAccounts(): HasMany
    {
        return $this->hasMany(TradingAccount::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }
}
