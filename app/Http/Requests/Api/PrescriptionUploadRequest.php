<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PrescriptionUploadRequest extends FormRequest
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'prescriptions' => ['nullable', 'array', 'max:10'],
            'prescriptions.*' => [
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'mimetypes:image/jpeg,image/png,application/pdf',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'prescriptions.array' => 'The Prescriptions must be a list of files',
            'prescriptions.max' => 'At most 10 prescriptions can be uploaded at once',
            'prescriptions.*.file' => 'Each Prescription must be an uploaded file',
            'prescriptions.*.mimes' => 'Each Prescription must be a JPG, PNG or PDF file',
            'prescriptions.*.mimetypes' => 'Each Prescription must be a JPG, PNG or PDF file',
            'prescriptions.*.max' => 'Each Prescription must not be larger than 5 MB',
        ];
    }
}
