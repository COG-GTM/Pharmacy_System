<?php

namespace Tests\Feature;

use App\Jobs\AssignNewOrder;
use App\Jobs\ChangeOrderStatusJob;
use App\Jobs\OrderConfirmationJob;
use App\Jobs\RemoveOldBans;
use App\Jobs\WelcomeEmailJob;
use App\Models\Address;
use App\Models\Area;
use App\Models\Client;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use Cog\Laravel\Ban\Models\Ban;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class JobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_assign_new_order_sets_pharmacy_by_area_priority()
    {
        $area = Area::factory()->create();
        $lowPriorityPharmacy = Pharmacy::factory()->create(['area_id' => $area->id, 'priority' => 1]);
        $highPriorityPharmacy = Pharmacy::factory()->create(['area_id' => $area->id, 'priority' => 10]);
        $address = Address::factory()->create(['area_id' => $area->id]);
        $order = Order::factory()->create([
            'status' => 'New',
            'pharmacy_id' => null,
            'delivering_address_id' => $address->id,
        ]);

        (new AssignNewOrder())->handle();

        $order->refresh();
        $this->assertEquals($highPriorityPharmacy->id, $order->pharmacy_id);
        $this->assertEquals('Processing', $order->status);
    }

    public function test_change_order_status_confirmed_to_delivered()
    {
        $order = Order::factory()->create(['status' => 'Confirmed']);

        (new ChangeOrderStatusJob())->handle();

        $order->refresh();
        $this->assertEquals('Delivered', $order->status);
    }

    public function test_remove_old_bans_removes_30_day_old_bans()
    {
        $user = User::factory()->create();
        $user->assignRole('doctor');
        $user->ban(['comment' => 'Old ban']);

        $ban = Ban::first();
        $ban->created_at = now()->subDays(31);
        $ban->save();

        (new RemoveOldBans())->handle();

        $this->assertFalse($user->fresh()->isBanned());
    }

    public function test_order_confirmation_job_sends_notification()
    {
        Notification::fake();

        $user = User::factory()->create();
        $user->assignRole('client');
        $client = Client::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create(['user_id' => $user->id]);

        (new OrderConfirmationJob($client, $order))->handle();

        Notification::assertSentTo($client, \App\Notifications\OrderConfirmationNotification::class);
    }

    public function test_welcome_email_job_sends_notification()
    {
        Notification::fake();

        $user = User::factory()->create();
        $user->assignRole('client');
        $client = Client::factory()->create(['user_id' => $user->id]);

        (new WelcomeEmailJob($client))->handle();

        Notification::assertSentTo($client, \App\Notifications\WelcomeEmailNotification::class);
    }
}
