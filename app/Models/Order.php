<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PhpParser\Node\Attribute;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'doctor_id',
        'delivering_address_id',
        'pharmacy_id',
        'is_insured',
        'status',
        'creator_type',
        'quantity',
        'medicine_id',
        'price'

    ];
    protected $casts = [
        'created_at' => 'date:Y-m-d',
        'updated_at' => 'date:Y-m-d',
    ];
    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class, 'pharmacy_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function medicines(): BelongsToMany
    {
        return $this->belongsToMany(Medicine::class, 'orders_medicines', 'order_id', 'medicine_id')->withPivot('quantity');
    }

    public static function totalPrice($quantity, $orderMedicine)
    {
        $totalPrice = 0;
        for ($i = 0; $i < count($orderMedicine ?? []); $i++) {
            $price = Medicine::where('id', $orderMedicine[$i])->first()->price;
            $totalPrice += $price * $quantity[$i];
        }
        return $totalPrice/100; //convert from cent to dollars
    }

    public static function createOrderMedicine($order, $quantity, $orderMedicine)
    {
        for ($i = 0; $i < count($orderMedicine); $i++) {
            $id = Medicine::where('id', $orderMedicine[$i])->first()->id;
            $order->medicines($id)->attach($orderMedicine[$i], ['quantity' => $quantity[$i]]);
        }
    }
    public function client()
    {
        return $this->belongsTo(Client::class, 'user_id');
    }
    public function address()
    {
        return $this->belongsTo(Address::class, 'delivering_address_id');
    }
    public function orderPrescription()
    {
        return $this->hasMany(OrderPrescription::class);
    }

    public static function updateOrderMedicine($order, $editedQuantity, $editedOrderMedicine)
    {
        if (!empty($editedOrderMedicine)) {
            $quantities = array_values((array) $editedQuantity);
            $medicines = [];
            foreach (array_values($editedOrderMedicine) as $i => $medicineId) {
                $id = Medicine::where('id', $medicineId)->first()->id;
                $medicines[$id] = ['quantity' => ($medicines[$id]['quantity'] ?? 0) + (int) ($quantities[$i] ?? 0)];
            }
            $order->medicines()->sync($medicines);
        }
        $order->price = self::orderTotalPrice($order);
    }

    public static function orderTotalPrice($order)
    {
        $total = $order->medicines()->get()->sum(fn ($medicine) => $medicine->price * $medicine->pivot->quantity);
        return $total / 100; //convert from cent to dollars
    }
}
