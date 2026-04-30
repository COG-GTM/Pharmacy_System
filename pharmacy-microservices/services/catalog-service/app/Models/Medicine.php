<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'quantity', 'price'];

    public static function calculateTotalPrice(array $medicineIds, array $quantities): float
    {
        $totalPrice = 0;
        for ($i = 0; $i < count($medicineIds); $i++) {
            $medicine = self::findOrFail($medicineIds[$i]);
            $totalPrice += $medicine->price * $quantities[$i];
        }
        return $totalPrice / 100;
    }
}
