<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueSummary extends Model
{
    protected $table = 'revenue_summary';

    protected $fillable = ['pharmacy_id', 'total_orders', 'total_revenue', 'period'];
}
