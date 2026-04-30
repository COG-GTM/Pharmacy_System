<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStats extends Model
{
    protected $table = 'order_stats';

    protected $fillable = [
        'order_id', 'pharmacy_id', 'user_id', 'status', 'price', 'delivered_at', 'created_at',
    ];
}
