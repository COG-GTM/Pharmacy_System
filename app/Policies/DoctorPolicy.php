<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Doctor;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DoctorPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function create(User $user, $pharmacyId = null)
    {
        return $user->hasRole('pharmacy') && $this->ownsPharmacy($user, $pharmacyId);
    }

    public function view(User $user, Doctor $doctor)
    {
        return $this->ownsDoctor($user, $doctor) || $this->isDoctor($user, $doctor);
    }

    public function update(User $user, Doctor $doctor)
    {
        return $this->ownsDoctor($user, $doctor) || $this->isDoctor($user, $doctor);
    }

    public function delete(User $user, Doctor $doctor)
    {
        return $this->ownsDoctor($user, $doctor);
    }

    public function ban(User $user, Doctor $doctor)
    {
        return $this->ownsDoctor($user, $doctor);
    }

    public function unban(User $user, Doctor $doctor)
    {
        return $this->ownsDoctor($user, $doctor);
    }

    private function ownsDoctor(User $user, Doctor $doctor)
    {
        return $user->hasRole('pharmacy') && $this->ownsPharmacy($user, $doctor->pharmacy_id);
    }

    private function ownsPharmacy(User $user, $pharmacyId)
    {
        if ($pharmacyId === null || $pharmacyId === '') {
            return false;
        }

        return Pharmacy::where('user_id', $user->id)
            ->where('id', $pharmacyId)
            ->exists();
    }

    private function isDoctor(User $user, Doctor $doctor)
    {
        return $user->hasRole('doctor') && (int) $doctor->user_id === (int) $user->id;
    }
}
