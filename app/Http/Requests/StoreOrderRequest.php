<?php

namespace App\Http\Requests;

use App\Models\Medicine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'user_id' => ['exists:users,id'],
            'delivering_address_id' => ['exists:addresses,id'],
            'pharmacy_id' => ['exists:pharmacies,id'],
            'status' => ['required', Rule::in(['New', 'Processing', 'WaitingForUserConfirmation', 'Canceled', 'Confirmed', 'Delivered'])],
            'creator_type' => ['required', Rule::in(['client', 'doctor', 'pharmacy'])],
        ];

    }
    public function messages()
    {
        return [
            'user_id.required' => 'userName is required',
            'delivering_address_id.required' => 'check client address',
            'status.required' => 'status is required',
            'creator_type.required' => 'creator type is required',
            'pharmacy_id.required' => 'pharmacy is required',
            'doctor_id.required' => 'doctor is required',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $quantities = array_values((array) $this->input('quantity', []));
            $requested = [];
            foreach (array_values((array) $this->input('medicine_id', [])) as $i => $medicineId) {
                $requested[$medicineId] = ($requested[$medicineId] ?? 0) + (int) ($quantities[$i] ?? 0);
            }
            foreach ($requested as $medicineId => $quantity) {
                $medicine = is_numeric($medicineId) ? Medicine::find($medicineId) : null;
                if ($medicine && $quantity > $medicine->quantity) {
                    $validator->errors()->add('quantity', "Only {$medicine->quantity} {$medicine->name} in stock, {$quantity} requested.");
                }
            }
        });
    }
}
