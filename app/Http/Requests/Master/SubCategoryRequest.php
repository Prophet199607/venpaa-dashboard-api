<?php

namespace App\Http\Requests\Master;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class SubCategoryRequest extends FormRequest
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
        $subCategoryCode = $this->route('scat_code');

        return [
            'scat_code' => [
                'required',
                'string',
                Rule::unique('sub_categories', 'scat_code')->ignore($subCategoryCode, 'scat_code'),
            ],
            'scat_name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'cat_code' => 'required|string|max:255',
            'status' => 'nullable|integer',
        ];
    }
}
