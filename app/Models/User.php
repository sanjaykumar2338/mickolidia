<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'plan_type',
        'account_size',
        'payment_amount',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_size' => 'integer',
            'payment_amount' => 'decimal:2',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function tradingAccounts(): HasMany
    {
        return $this->hasMany(TradingAccount::class);
    }

    public function latestTradingAccount(): HasOne
    {
        return $this->hasOne(TradingAccount::class)->latestOfMany();
    }

    public function challengeTradingAccounts(): HasMany
    {
        return $this->hasMany(TradingAccount::class)->where('is_trial', false);
    }

    public function latestChallengeTradingAccount(): HasOne
    {
        return $this->hasOne(TradingAccount::class)
            ->where('is_trial', false)
            ->latestOfMany();
    }

    public function trialAccounts(): HasMany
    {
        return $this->hasMany(TradingAccount::class)->where('is_trial', true);
    }

    public function latestTrialAccount(): HasOne
    {
        return $this->hasOne(TradingAccount::class)
            ->where('is_trial', true)
            ->latestOfMany();
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function challengePurchases(): HasMany
    {
        return $this->hasMany(ChallengePurchase::class);
    }

    public function latestOrder(): HasOne
    {
        return $this->hasOne(Order::class)->latestOfMany();
    }

    public function latestChallengePurchase(): HasOne
    {
        return $this->hasOne(ChallengePurchase::class)->latestOfMany();
    }
}
