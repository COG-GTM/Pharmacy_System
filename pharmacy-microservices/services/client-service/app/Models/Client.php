<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'user_id', 'gender', 'date_of_birth', 'avatar_image', 'phone'];

    public function address()
    {
        return $this->hasMany(Address::class);
    }
}
