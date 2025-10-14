<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
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
        $supplierCode = $this->route('sup_code');

        return [
            'sup_code' => [
                'required',
                'string',
                Rule::unique('suppliers', 'sup_code')->ignore($supplierCode, 'sup_code'),
            ],
            'sup_name' => 'required|string',
            'company' => 'nullable|string',
            'address' => 'nullable|string',
            'mobile' => 'nullable|string',
            'telephone' => 'nullable|string',
            'email' => 'nullable|string|email',
            'description' => 'nullable|string',
            'sup_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'status' => 'nullable|integer',
        ];
    }
}