<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'doctor_id', 'delivering_address_id', 'pharmacy_id',
        'is_insured', 'status', 'creator_type', 'quantity', 'medicine_id', 'price',
    ];

    public function medicines()
    {
        return $this->belongsToMany(OrderMedicine::class, 'orders_medicines', 'order_id', 'medicine_id')
            ->withPivot('quantity');
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }
}
