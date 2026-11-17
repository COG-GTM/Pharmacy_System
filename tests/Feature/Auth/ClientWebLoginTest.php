<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientWebLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->withoutVite();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamp('banned_at')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function userWithRole(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => "$role@example.com",
            'password' => Hash::make('secret123'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    public function test_client_web_login_lands_on_a_page_they_can_open()
    {
        $this->userWithRole('client');

        $response = $this->post('/login', ['email' => 'client@example.com', 'password' => 'secret123']);
        $response->assertRedirect('/home');

        $this->get('/home')
            ->assertOk()
            ->assertSee(route('logout'), false)
            ->assertSee('mobile app')
            ->assertSee('<div class="sidebar"', false)
            ->assertSee('href="' . route('home') . '"', false)
            ->assertDontSee('href="' . route('index') . '"', false);
    }

    public function test_forbidden_page_offers_logout_to_a_signed_in_user()
    {
        $client = $this->userWithRole('client');

        $this->actingAs($client)->get('/')
            ->assertForbidden()
            ->assertSee('action="' . route('logout') . '"', false)
            ->assertSee('href="' . route('home') . '"', false)
            ->assertDontSee('client@example.com');

        $this->actingAs($client)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_staff_routes_stay_closed_to_clients()
    {
        $client = $this->userWithRole('client');

        foreach (['/', '/orders', '/medicines', '/doctors', '/clients', '/areas'] as $uri) {
            $this->actingAs($client)->get($uri)->assertForbidden();
        }
    }

    public function test_staff_still_reach_home_after_login()
    {
        $this->userWithRole('admin');

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'secret123'])
            ->assertRedirect('/home');

        $this->get('/home')->assertOk()->assertDontSee('mobile app');
    }
}
