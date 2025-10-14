<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class LocationRequest extends FormRequest
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
     * @return array
     */
    public function rules()
    {
        $locationCode = $this->route('loca_code');

        return [
            'loca_code' => [
                'required',
                'string',
                Rule::unique('locations', 'loca_code')->ignore($locationCode, 'loca_code'),
            ],
            'loca_name' => 'required|string|max:255',
            'location_type' => [
                'required',
                'string',
                Rule::in(['Branch', 'Exhibition']),
            ],
            'delivery_address' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
}