<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Doctor;
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

    public function create(User $user, Doctor $doctor)
    {
        return $this->ownsDoctor($user, $doctor);
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

    /**
     * A pharmacy may only act on doctors assigned to its own pharmacy.
     */
    private function ownsDoctor(User $user, Doctor $doctor)
    {
        return $user->hasRole('pharmacy')
            && $user->pharmacy !== null
            && (int) $doctor->pharmacy_id === (int) $user->pharmacy->id;
    }

    /**
     * A doctor may only act on their own record.
     */
    private function isDoctor(User $user, Doctor $doctor)
    {
        return $user->hasRole('doctor') && (int) $doctor->user_id === (int) $user->id;
    }
}
