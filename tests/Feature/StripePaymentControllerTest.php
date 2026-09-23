<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stripe\Charge;
use Stripe\Service\ChargeService;
use Stripe\StripeClient;
use Tests\TestCase;

class StripePaymentControllerTest extends TestCase
{
    private const MIGRATIONS = [
        'database/migrations/2014_10_12_000000_create_users_table.php',
        'database/migrations/2017_03_04_000000_create_bans_table.php',
        'database/migrations/2023_03_27_211933_create_orders_table.php',
        'database/migrations/2023_03_28_225313_create_permission_tables.php',
        'database/migrations/2023_04_03_180722_add_email_verified_at_to_users_table.php',
        'database/migrations/2023_04_04_151455_add_last_login_to_users_table.php',
        'database/migrations/2023_04_04_233733_add_banned_at_to_users_table.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');

        foreach (self::MIGRATIONS as $migration) {
            Artisan::call('migrate', ['--path' => $migration, '--force' => true]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('client', 'web');
    }

    public function test_client_cannot_open_the_payment_page_of_another_users_order(): void
    {
        $attacker = $this->createClient();
        $order = $this->createOrder($this->createClient());

        $response = $this->actingAs($attacker)->get('/stripe/' . $order->id);

        $response->assertNotFound();
    }

    public function test_client_cannot_confirm_another_users_order(): void
    {
        $attacker = $this->createClient();
        $order = $this->createOrder($this->createClient());

        $response = $this->actingAs($attacker)->post('/stripe', [
            'order_id' => $order->id,
            'stripeToken' => 'tok_visa',
        ]);

        $response->assertNotFound();
        $this->assertSame('WaitingForUserConfirmation', $order->fresh()->status);
    }

    public function test_owner_cannot_confirm_an_order_without_submitting_a_payment(): void
    {
        $owner = $this->createClient();
        $order = $this->createOrder($owner);

        $response = $this->actingAs($owner)->post('/stripe', ['order_id' => $order->id]);

        $response->assertSessionHasErrors('stripeToken');
        $this->assertSame('WaitingForUserConfirmation', $order->fresh()->status);
    }

    public function test_owner_order_stays_unconfirmed_when_the_charge_cannot_be_made(): void
    {
        config(['services.stripe.secret' => null]);
        $owner = $this->createClient();
        $order = $this->createOrder($owner);

        $response = $this->actingAs($owner)->post('/stripe', [
            'order_id' => $order->id,
            'stripeToken' => 'tok_visa',
        ]);

        $response->assertRedirect();
        $this->assertSame('WaitingForUserConfirmation', $order->fresh()->status);
    }

    public function test_owner_order_is_confirmed_once_stripe_reports_a_succeeded_charge(): void
    {
        config(['services.stripe.secret' => 'sk_test_fake']);
        $owner = $this->createClient();
        $order = $this->createOrder($owner);
        $this->fakeStripeCharge(['status' => 'succeeded', 'amount' => 2550, 'currency' => 'usd']);

        $response = $this->actingAs($owner)->post('/stripe', [
            'order_id' => $order->id,
            'stripeToken' => 'tok_visa',
        ]);

        $response->assertOk();
        $this->assertSame('Confirmed', $order->fresh()->status);
    }

    public function test_order_is_not_confirmed_when_stripe_charges_a_different_amount(): void
    {
        config(['services.stripe.secret' => 'sk_test_fake']);
        $owner = $this->createClient();
        $order = $this->createOrder($owner);
        $this->fakeStripeCharge(['status' => 'succeeded', 'amount' => 100, 'currency' => 'usd']);

        $response = $this->actingAs($owner)->post('/stripe', [
            'order_id' => $order->id,
            'stripeToken' => 'tok_visa',
        ]);

        $response->assertRedirect();
        $this->assertSame('WaitingForUserConfirmation', $order->fresh()->status);
    }

    private function fakeStripeCharge(array $charge): void
    {
        $charges = Mockery::mock(ChargeService::class);
        $charges->shouldReceive('create')
            ->once()
            ->withArgs(function (array $params, array $options) {
                return $params['amount'] === 2550
                    && $params['currency'] === 'usd'
                    && isset($options['idempotency_key']);
            })
            ->andReturn(Charge::constructFrom($charge));

        $client = Mockery::mock(StripeClient::class);
        $client->shouldReceive('getService')->with('charges')->andReturn($charges);
        $this->app->instance(StripeClient::class, $client);
    }

    private function createClient(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }

    private function createOrder(User $user): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'pharmacy_id' => 1,
            'status' => 'WaitingForUserConfirmation',
            'is_insured' => 0,
            'creator_type' => 'client',
            'price' => 25.5,
        ]);
    }
}
