<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PharmacyPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function view(User $user, Pharmacy $pharmacy)
    {
        return $this->owns($user, $pharmacy);
    }

    public function update(User $user, Pharmacy $pharmacy)
    {
        return $this->owns($user, $pharmacy);
    }

    /**
     * A pharmacy actor may only act on the pharmacy it owns.
     */
    private function owns(User $user, Pharmacy $pharmacy)
    {
        return $user->hasRole('pharmacy') && (int) $pharmacy->user_id === (int) $user->id;
    }
}
