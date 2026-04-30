<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderMedicine extends Model
{
    protected $table = 'orders_medicines';

    protected $fillable = ['order_id', 'medicine_id', 'quantity'];
}
