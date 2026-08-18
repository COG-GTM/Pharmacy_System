<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RemoveOldBans;
use App\Models\User;
use Cog\Laravel\Ban\Models\Ban;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoveOldBansTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Documents a live bug: the job calls $ban->user, which is not a relation on
     * the Ban model (the banned entity is exposed as bannable), so expired bans
     * are never lifted.
     */
    public function test_it_fails_to_lift_bans_older_than_thirty_days()
    {
        $user = User::factory()->create();
        $user->ban();
        Ban::query()->update(['created_at' => now()->subDays(31)]);

        try {
            (new RemoveOldBans)->handle();
            $this->fail('Expected the job to fail while lifting an expired ban.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('unban() on null', $e->getMessage());
        }

        $this->assertTrue($user->fresh()->isBanned());
        $this->assertDatabaseCount('bans', 1);
    }

    public function test_it_keeps_recent_bans()
    {
        $user = User::factory()->create();
        $user->ban();

        (new RemoveOldBans)->handle();

        $this->assertTrue($user->fresh()->isBanned());
        $this->assertDatabaseCount('bans', 1);
    }
}
