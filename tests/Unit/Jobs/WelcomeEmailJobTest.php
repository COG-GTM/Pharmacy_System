<?php

namespace Tests\Unit\Jobs;

use App\Jobs\WelcomeEmailJob;
use App\Models\User;
use App\Notifications\WelcomeEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WelcomeEmailJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_the_welcome_notification_to_the_client()
    {
        Notification::fake();
        $user = User::factory()->create();

        (new WelcomeEmailJob($user))->handle();

        Notification::assertSentTo($user, WelcomeEmailNotification::class);
    }

    public function test_it_only_notifies_the_given_client()
    {
        Notification::fake();
        $user = User::factory()->create();
        $other = User::factory()->create();

        (new WelcomeEmailJob($user))->handle();

        Notification::assertNotSentTo($other, WelcomeEmailNotification::class);
    }
}
