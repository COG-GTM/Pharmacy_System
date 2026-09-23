<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Order $order)
    {
        return $this->belongsToTenant($user, $order);
    }

    public function update(User $user, Order $order)
    {
        return $this->belongsToTenant($user, $order);
    }

    public function delete(User $user, Order $order)
    {
        return $this->belongsToTenant($user, $order);
    }

    /**
     * The pharmacy an order belongs to is the tenant boundary enforced on the
     * order listing by OrdersDataTable::query().
     */
    protected function belongsToTenant(User $user, Order $order)
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('pharmacy')) {
            return $user->pharmacy !== null && (int) $order->pharmacy_id === (int) $user->pharmacy->id;
        }

        if ($user->hasRole('doctor')) {
            return $user->doctor !== null && (int) $order->pharmacy_id === (int) $user->doctor->pharmacy_id;
        }

        return false;
    }
}
