<?php

namespace App\Http\Requests\Master;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class BookRequest extends FormRequest
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

            'pack_size' => 'nullable|string',
            'purchase_price' => 'nullable|numeric',
            'selling_price' => 'nullable|numeric',
            'marked_price' => 'nullable|numeric',
            'wholesale_price' => 'nullable|numeric',


            'title_in_other_language' => 'required|string',
            'tamil_description' => 'required|string',
            'book_type' => 'nullable|exists:book_types,bkt_code',
            'publisher' => 'required|exists:publishers,pub_code',
            'supplier' => 'nullable|string',
            'author' => 'required|string',

            'isbn' => [
                'nullable',
                'string',
                Rule::unique('products', 'isbn')
                    ->ignore($prodCode, 'prod_code')
                    ->whereNotNull('isbn'),
            ],
            'publish_year' => 'nullable|digits:4',
            'alert_qty' => 'nullable|integer',
            'width' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'depth' => 'nullable|numeric',
            'weight' => 'nullable|integer',
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

    /**
     * Get custom error messages for validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'isbn.unique' => 'This ISBN is already in use by another book.',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('author') && is_array($this->author)) {
            $this->merge([
                'author' => implode(',', $this->author)
            ]);
        }
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
        if ($this->has('unconfirmed_price')) {
            $this->merge([
                'unconfirm_price' => $this->boolean('unconfirmed_price')
            ]);
        }
    }
}
