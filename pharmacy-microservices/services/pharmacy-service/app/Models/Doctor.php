<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Doctor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['id', 'user_id', 'avatar_image', 'pharmacy_id', 'is_banned', 'banned_at'];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
