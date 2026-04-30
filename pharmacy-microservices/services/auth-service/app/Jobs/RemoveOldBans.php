<?php

namespace App\Jobs;

use Cog\Laravel\Ban\Models\Ban;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RemoveOldBans implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        Ban::query()
            ->where('created_at', '<', now()->subDays(30))
            ->get()
            ->each(function ($ban) {
                $ban->bannable->unban();
                $ban->delete();
            });
    }
}
