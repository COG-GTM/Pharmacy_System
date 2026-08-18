<?php

namespace Tests\Concerns;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithRoles
{
    /**
     * Create (or fetch) a role of the web guard.
     *
     * @param  string  $name
     * @return \Spatie\Permission\Models\Role
     */
    protected function createRole($name)
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Role::findOrCreate($name, 'web');
    }

    /**
     * Create a user holding the given role.
     *
     * @param  string  $role
     * @param  array<string, mixed>  $attributes
     * @return \App\Models\User
     */
    protected function userWithRole($role, array $attributes = [])
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($this->createRole($role));

        return $user->fresh();
    }

    /**
     * Authenticate as a newly created user holding the given role.
     *
     * @param  string  $role
     * @param  array<string, mixed>  $attributes
     * @return \App\Models\User
     */
    protected function actingAsRole($role, array $attributes = [])
    {
        $user = $this->userWithRole($role, $attributes);
        $this->actingAs($user);

        return $user;
    }
}
