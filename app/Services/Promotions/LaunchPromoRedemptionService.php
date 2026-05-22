<?php

namespace App\Services\Promotions;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LaunchPromoRedemptionService
{
    public function customerHasRedeemed(User $user, string $email, string $promoCode, ?int $ignoreOrderId = null): bool
    {
        if (! $this->singleUseEnabled($promoCode)) {
            return false;
        }

        if (! $this->canReadOrderRedemptions()) {
            return false;
        }

        if ($this->isPrivateCoupon($promoCode)) {
            return $this->redeemedOrdersForCode($promoCode)
                ->when($ignoreOrderId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreOrderId))
                ->exists();
        }

        return $this->redeemedOrdersForCustomer($user, $email, $promoCode)
            ->when($ignoreOrderId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreOrderId))
            ->exists();
    }

    public function anotherCustomerOrderHasRedeemed(Order $order): bool
    {
        $promoCode = $this->promoCodeForOrder($order);

        if ($promoCode === null || ! $this->singleUseEnabled($promoCode) || ! $this->canReadOrderRedemptions()) {
            return false;
        }

        if ($this->isPrivateCoupon($promoCode)) {
            return $this->redeemedOrdersForCode($promoCode)
                ->whereKeyNot($order->id)
                ->exists();
        }

        $user = $order->user()->first();

        if (! $user instanceof User) {
            return false;
        }

        return $this->customerHasRedeemed($user, $order->email, $promoCode, $order->id);
    }

    public function promoCodeHasRedeemed(string $promoCode, ?int $ignoreOrderId = null): bool
    {
        if (! $this->singleUseEnabled($promoCode) || ! $this->canReadOrderRedemptions()) {
            return false;
        }

        return $this->redeemedOrdersForCode($promoCode)
            ->when($ignoreOrderId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreOrderId))
            ->exists();
    }

    public function stampRedeemed(Order $order): void
    {
        $promoCode = $this->promoCodeForOrder($order);

        if ($promoCode === null) {
            return;
        }

        $metadata = $order->metadata ?? [];
        $launchPromo = (array) data_get($metadata, 'launch_promo', []);

        data_set($metadata, 'launch_promo', array_merge($launchPromo, [
            'code' => $promoCode,
            'applied' => true,
            'redeemed' => true,
            'redeemed_at' => now()->toIso8601String(),
            'redeemed_order_id' => $order->id,
        ]));

        $order->forceFill([
            'metadata' => $metadata,
        ])->save();
    }

    private function redeemedOrdersForCustomer(User $user, string $email, string $promoCode): Builder
    {
        return $this->redeemedOrdersForCode($promoCode)
            ->where(function (Builder $query) use ($user, $email): void {
                $query->where('user_id', $user->id)
                    ->orWhereRaw('LOWER(email) = ?', [strtolower($email)]);
            });
    }

    private function redeemedOrdersForCode(string $promoCode): Builder
    {
        return Order::query()
            ->whereRaw($this->jsonStringEqualsExpression('metadata', '$.launch_promo.code'), [strtolower($promoCode)])
            ->where(function (Builder $query): void {
                $query->where('payment_status', Order::PAYMENT_PAID)
                    ->orWhereRaw($this->jsonTruthyExpression('metadata', '$.launch_promo.redeemed'));
            });
    }

    private function canReadOrderRedemptions(): bool
    {
        $table = (new Order)->getTable();

        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'metadata')
            && Schema::hasColumn($table, 'payment_status');
    }

    private function jsonStringEqualsExpression(string $column, string $path): string
    {
        $wrapped = DB::connection()->getQueryGrammar()->wrap($column);

        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "LOWER(JSON_UNQUOTE(JSON_EXTRACT({$wrapped}, '{$path}'))) = ?",
            'pgsql' => "LOWER({$wrapped}->'launch_promo'->>'code') = ?",
            'sqlsrv' => "LOWER(JSON_VALUE({$wrapped}, '{$path}')) = ?",
            default => "LOWER(CAST(json_extract({$wrapped}, '{$path}') AS TEXT)) = ?",
        };
    }

    private function jsonTruthyExpression(string $column, string $path): string
    {
        $wrapped = DB::connection()->getQueryGrammar()->wrap($column);

        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "LOWER(JSON_UNQUOTE(JSON_EXTRACT({$wrapped}, '{$path}'))) IN ('true', '1')",
            'pgsql' => "LOWER({$wrapped}->'launch_promo'->>'redeemed') IN ('true', '1')",
            'sqlsrv' => "LOWER(JSON_VALUE({$wrapped}, '{$path}')) IN ('true', '1')",
            default => "LOWER(CAST(json_extract({$wrapped}, '{$path}') AS TEXT)) IN ('true', '1')",
        };
    }

    private function promoCodeForOrder(Order $order): ?string
    {
        $promoCode = trim((string) data_get($order->metadata, 'launch_promo.code', ''));

        return $promoCode !== '' ? $promoCode : null;
    }

    private function singleUseEnabled(string $promoCode): bool
    {
        if ($this->isPrivateCoupon($promoCode)) {
            return (bool) config('wolforix.private_coupon.single_use', true);
        }

        return (bool) config('wolforix.launch_discount.single_use_per_customer', false);
    }

    private function isPrivateCoupon(string $promoCode): bool
    {
        $privateCode = trim((string) config('wolforix.private_coupon.code', ''));

        return $privateCode !== '' && strcasecmp($promoCode, $privateCode) === 0;
    }
}
