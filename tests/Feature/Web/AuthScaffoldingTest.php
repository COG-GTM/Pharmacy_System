<?php

namespace Tests\Feature\Web;

use App\Jobs\WelcomeEmailJob;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

class AuthScaffoldingTest extends TestCase
{
    use RefreshDatabase, InteractsWithUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_user_can_log_in_with_valid_credentials()
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertRedirect('/home');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_a_wrong_password()
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_user_can_log_out()
    {
        $this->actingAsRole('admin');

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_registration_creates_a_user_and_fires_the_registered_event()
    {
        Event::fake([Registered::class]);

        $this->post('/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect('/home');

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
        Event::assertDispatched(Registered::class);
    }

    public function test_a_password_reset_link_is_emailed()
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/password/email', ['email' => $user->email])
            ->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_the_confirm_password_page_is_only_available_to_authenticated_users()
    {
        $this->get('/password/confirm')->assertRedirect('/login');

        $this->actingAsRole('admin');

        $this->get('/password/confirm')->assertOk();
    }

    public function test_a_signed_verification_link_verifies_the_email_address()
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        Bus::fake();

        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertJson(['message' => 'Email verified successfully']);

        $this->assertNotNull($user->fresh()->email_verified_at);
        Bus::assertDispatched(WelcomeEmailJob::class);
    }
}
