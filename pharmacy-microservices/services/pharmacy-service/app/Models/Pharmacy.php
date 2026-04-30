<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pharmacy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['id', 'user_id', 'pharmacy_name', 'avatar_image', 'area_id', 'priority'];

    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }
}
