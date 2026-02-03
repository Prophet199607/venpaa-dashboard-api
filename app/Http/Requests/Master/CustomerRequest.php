<?php

namespace App\Http\Requests\Master;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
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
        $customerCode = $this->route('customer_code');

        return [
            'customer_code' => [
                'required',
                'string',
                Rule::unique('customers', 'customer_code')->ignore($customerCode, 'customer_code'),
            ],

            'customer_name' => 'required|string|max:255',
            'mobile'        => 'nullable|string|max:20',
            'nic'           => 'nullable|string|max:50',
            'dob'           => 'nullable|date',
            'is_active'     => 'nullable|boolean',
            'is_credit'     => 'nullable|boolean',
            'credit_limit'  => 'nullable|numeric|min:0',
            'credit_period' => 'nullable|integer|min:0',
            'address'       => 'nullable|string',
            'is_vat'        => 'nullable|boolean',
            'vat_number'    => 'nullable|string|max:50',
        ];
    }
}
