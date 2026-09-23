<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PharmacyPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Pharmacy $pharmacy): bool
    {
        return $this->owns($user, $pharmacy);
    }

    public function update(User $user, Pharmacy $pharmacy): bool
    {
        return $this->owns($user, $pharmacy);
    }

    private function owns(User $user, Pharmacy $pharmacy): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('pharmacy') && $pharmacy->user_id === $user->id;
    }
}
