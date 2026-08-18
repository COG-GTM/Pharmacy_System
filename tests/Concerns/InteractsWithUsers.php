<?php

namespace Tests\Concerns;

use App\Models\User;
use Spatie\Permission\Models\Role;

trait InteractsWithUsers
{
    /**
     * Create a verified user holding the given role.
     */
    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    /**
     * Authenticate as a verified user holding the given role.
     */
    protected function actingAsRole(string $role, array $attributes = []): User
    {
        $user = $this->userWithRole($role, $attributes);
        $this->actingAs($user);

        return $user;
    }
}
