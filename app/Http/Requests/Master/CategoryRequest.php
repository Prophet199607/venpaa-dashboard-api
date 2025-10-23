<?php

namespace App\Http\Requests\Master;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
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
        $categoryCode = $this->route('cat_code');

        return [
            'cat_code' => [
                'required',
                'string',
                Rule::unique('categories', 'cat_code')->ignore($categoryCode, 'cat_code'),
            ],
            'cat_name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'cat_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'status' => 'nullable|integer',
        ];
    }
}
