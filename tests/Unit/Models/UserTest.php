<?php

namespace Tests\Unit\Models;

use App\Models\Client;
use App\Models\Doctor;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_hides_the_password_and_casts_the_verification_timestamp()
    {
        $user = User::factory()->create();

        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->email_verified_at);
    }

    public function test_it_exposes_its_client_pharmacy_doctor_and_orders()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $pharmacy = Pharmacy::factory()->create(['user_id' => $user->id]);
        $doctor = Doctor::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->client->is($client));
        $this->assertTrue($user->pharmacy->is($pharmacy));
        $this->assertTrue($user->owns->is($pharmacy));
        $this->assertTrue($user->doctor->is($doctor));
        $this->assertEquals([$order->id], $user->orders->pluck('id')->all());
    }

    public function test_it_can_be_assigned_a_role()
    {
        Role::findOrCreate('pharmacy', 'web');
        $user = User::factory()->create();

        $user->assignRole('pharmacy');

        $this->assertTrue($user->fresh()->hasRole('pharmacy'));
    }

    public function test_it_is_bannable()
    {
        $user = User::factory()->create();

        $user->ban();

        $this->assertTrue($user->fresh()->isBanned());
    }

    public function test_get_email_for_verification_returns_the_email()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->assertSame('user@example.com', $user->getEmailForVerification());
    }
}
