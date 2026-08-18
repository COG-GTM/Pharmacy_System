<?php

namespace Tests\Feature\Web;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client', 'web');
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/clients')->assertRedirect('/login');
    }

    public function test_only_admins_may_manage_clients()
    {
        $this->actingAsRole('pharmacy');

        $this->get('/clients')->assertForbidden();
    }

    public function test_show_returns_the_client_with_its_user_and_addresses()
    {
        $this->actingAsRole('admin');
        $client = Client::factory()->create();

        $this->get("/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('client.id', $client->id)
            ->assertJsonPath('user.id', $client->user_id);
    }

    public function test_store_creates_a_user_and_a_client_with_the_client_role()
    {
        $this->actingAsRole('admin');

        $this->post('/clients', [
            'name' => 'Nour Hassan',
            'email' => 'nour@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'id' => '29001011234567',
            'gender' => 'Female',
            'date_of_birth' => '1990-01-01',
            'phone' => '01012345678',
        ])
            ->assertRedirect(route('clients.index'))
            ->assertSessionHas('success');

        $user = User::where('email', 'nour@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('client'));
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertDatabaseHas('clients', [
            'id' => '29001011234567',
            'user_id' => $user->id,
            'avatar_image' => 'default-avatar.jpg',
        ]);
    }

    public function test_store_rejects_an_invalid_national_id()
    {
        $this->actingAsRole('admin');

        $this->post('/clients', [
            'name' => 'Nour Hassan',
            'email' => 'nour@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'id' => '123',
            'gender' => 'Female',
            'date_of_birth' => '1990-01-01',
            'phone' => '01012345678',
        ])->assertSessionHasErrors('id');

        $this->assertDatabaseMissing('users', ['email' => 'nour@example.com']);
    }

    public function test_update_changes_the_client_and_its_user()
    {
        $this->actingAsRole('admin');
        $client = Client::factory()->create(['gender' => 'Male']);

        $this->put("/clients/{$client->id}", [
            'name' => 'Renamed Client',
            'email' => 'renamed@example.com',
            'id' => $client->id,
            'gender' => 'Female',
            'date_of_birth' => '1991-02-03',
            'phone' => '01112345678',
        ])
            ->assertRedirect(route('clients.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $client->user_id,
            'name' => 'Renamed Client',
            'email' => 'renamed@example.com',
        ]);
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'gender' => 'Female']);
    }

    public function test_destroy_removes_the_client_and_its_user()
    {
        $this->actingAsRole('admin');
        $client = Client::factory()->create();

        $this->delete("/clients/{$client->id}")
            ->assertRedirect(route('clients.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
        $this->assertDatabaseMissing('users', ['id' => $client->user_id]);
    }
}
