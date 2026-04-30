<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Cog\Contracts\Ban\Bannable as BannableInterface;
use Cog\Laravel\Ban\Traits\Bannable;

class User extends Authenticatable implements MustVerifyEmail, BannableInterface
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, Bannable;

    protected $fillable = [
        'id', 'name', 'email', 'password', 'email_verified_at', 'remember_token', 'last_login', 'banned_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
