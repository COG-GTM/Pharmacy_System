<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegistered
{
    use Dispatchable, SerializesModels;

    public User $user;
    public string $role;

    public function __construct(User $user, string $role)
    {
        $this->user = $user;
        $this->role = $role;
    }
}
