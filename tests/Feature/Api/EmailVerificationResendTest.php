<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationResendTest extends TestCase
{
    private function unverifiedUser(): User
    {
        $user = new User([
            'name' => 'Client One',
            'email' => 'client.one@example.com',
        ]);
        $user->id = 1;
        $user->email_verified_at = null;

        return $user;
    }

    public function test_guest_cannot_trigger_verification_emails(): void
    {
        Notification::fake();

        $response = $this->getJson('/api/email/resend/12345678901234');

        $response->assertStatus(401);
        Notification::assertNothingSent();
    }

    public function test_authenticated_user_can_resend_their_own_verification_email(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->unverifiedUser());

        $response = $this->getJson('/api/email/resend');

        $response->assertStatus(200);
        Notification::assertSentTo($this->unverifiedUser(), VerifyEmail::class);
    }

    public function test_authenticated_user_cannot_resend_for_another_client(): void
    {
        Notification::fake();

        $user = $this->unverifiedUser();
        $user->setRelation('client', new Client(['id' => '11111111111111']));
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/email/resend/99999999999999');

        $response->assertStatus(403);
        Notification::assertNothingSent();
    }

    public function test_verified_user_is_told_the_email_is_already_verified(): void
    {
        Notification::fake();

        $user = $this->unverifiedUser();
        $user->email_verified_at = now();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/email/resend');

        $response->assertStatus(422);
        Notification::assertNothingSent();
    }
}
