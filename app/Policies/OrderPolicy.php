<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function view(User $user, Order $order)
    {
        return $this->belongsToActingPharmacy($user, $order);
    }

    public function update(User $user, Order $order)
    {
        return $this->belongsToActingPharmacy($user, $order);
    }

    public function delete(User $user, Order $order)
    {
        return $this->belongsToActingPharmacy($user, $order);
    }

    /**
     * The pharmacy an order must belong to, mirroring OrdersDataTable::query().
     */
    private function belongsToActingPharmacy(User $user, Order $order)
    {
        if ($user->hasRole('pharmacy')) {
            return $user->pharmacy !== null && (int) $order->pharmacy_id === (int) $user->pharmacy->id;
        }

        if ($user->hasRole('doctor')) {
            return $user->doctor !== null && (int) $order->pharmacy_id === (int) $user->doctor->pharmacy_id;
        }

        return false;
    }
}
