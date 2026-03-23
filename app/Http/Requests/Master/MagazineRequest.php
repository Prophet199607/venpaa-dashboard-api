<?php

namespace App\Http\Requests\Master;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class MagazineRequest extends FormRequest
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
                $prodCode ? Rule::unique('products', 'prod_code')->ignore($prodCode, 'prod_code') : '',
            ],
            'prod_name' => [
                'required',
                'string',
                Rule::unique('products', 'prod_name')->ignore($prodCode, 'prod_code'),
            ],
            'short_description' => 'nullable|string',
            'department' => 'required|exists:departments,dep_code',
            'category' => 'required|exists:categories,cat_code',
            'sub_category' => 'required|string',
            'sub_category_l2' => 'nullable|string',

            'pack_size' => 'nullable|string',
            'purchase_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'marked_price' => 'nullable|numeric',
            'wholesale_price' => 'nullable|numeric',


            'title_in_other_language' => 'required|string',
            'tamil_description' => 'required|string',
            'publisher' => 'required|exists:publishers,pub_code',
            'supplier' => 'nullable|string',

            'publish_year' => 'nullable|digits:4',
            'issue_date' => 'required|date',
            'alert_qty' => 'nullable|integer',
            'width' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'depth' => 'nullable|numeric',
            'weight' => 'required|integer',
            'pages' => 'nullable|integer',
            'barcode' => 'nullable|string',
            'language' => 'nullable|string',
            'prod_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'nullable|string',
            'status' => 'nullable|integer',
            'unit_name' => 'nullable|string',
            'unconfirm_price' => 'nullable|boolean',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('supplier') && is_array($this->supplier)) {
            $this->merge([
                'supplier' => implode(',', $this->supplier)
            ]);
        }
        if ($this->has('sub_category') && is_array($this->sub_category)) {
            $this->merge([
                'sub_category' => implode(',', $this->sub_category)
            ]);
        }
        if ($this->has('sub_category_l2') && is_array($this->sub_category_l2)) {
            $this->merge([
                'sub_category_l2' => implode(',', $this->sub_category_l2)
            ]);
        }
        if ($this->has('unconfirmed_price')) {
            $this->merge([
                'unconfirm_price' => $this->boolean('unconfirmed_price')
            ]);
        }
    }
}
