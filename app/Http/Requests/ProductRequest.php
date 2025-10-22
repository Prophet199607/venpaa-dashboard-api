<?php

namespace App\Http\Requests;

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
            'book_type' => 'nullable|exists:book_types,bkt_code',
            'publisher' => 'nullable|exists:publishers,pub_code',
            'author' => 'nullable|exists:authors,auth_code',

            'isbn' => 'nullable|string',
            'publish_year' => 'nullable|digits:4',
            'pack_size' => 'nullable|string',
            'alert_qty' => 'nullable|integer',

            'width' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'depth' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
            'pages' => 'nullable|integer',
            'barcode' => 'nullable|string',
            'language' => 'nullable|string',
            'prod_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'nullable|string',
            'status' => 'nullable|integer',
        ];
    }
}
