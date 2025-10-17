<?php

namespace App\Http\Requests;

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
        $bookCode = $this->route("book_code");

        return [
            'book_code' => [
                'required',
                'string',
                Rule::unique('books', 'book_code')->ignore($bookCode, 'book_code'),
            ],
            'title' => 'required|string',
            'isbn' => 'nullable|string',
            'publish_year' => 'nullable|digits:4',

            'book_type' => 'required|exists:book_types,bkt_code',
            'department' => 'required|exists:departments,dep_code',
            'category' => 'required|exists:categories,cat_code',
            'sub_category' => 'required|exists:sub_categories,scat_code',
            'publisher' => 'required|exists:publishers,pub_code',
            'supplier' => 'required|exists:suppliers,sup_code',
            'author' => 'required|exists:authors,auth_code',

            'pack_size' => 'nullable|string',
            'alert_qty' => 'nullable|integer',

            'width' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'depth' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
            'pages' => 'nullable|integer',
            'barcode' => 'nullable|string',
            'language' => 'nullable|string',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'nullable|string',
            'status' => 'nullable|integer',
        ];
    }
}
