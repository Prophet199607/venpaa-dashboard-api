<?php

namespace App\Http\Requests\Master;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
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
        $prodCode = $this->route('prod_code');

        return [
            'prod_code' => [
                'required',
                'string',
                Rule::unique('products', 'prod_code')->ignore($prodCode, 'prod_code'),
            ],
            'prod_name' => 'required|string',
            'short_description' => 'nullable|string',
            'department' => 'required|exists:departments,dep_code',
            'category' => 'required|exists:categories,cat_code',
            'sub_category' => 'required|exists:sub_categories,scat_code',

            'supplier' => 'required|exists:suppliers,sup_code',
            'pack_size' => 'nullable|string',
            'alert_qty' => 'nullable|integer',

            'width' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'depth' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
            'barcode' => 'nullable|string',
            'prod_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'nullable|string',
            'status' => 'nullable|integer',
        ];
    }
}
