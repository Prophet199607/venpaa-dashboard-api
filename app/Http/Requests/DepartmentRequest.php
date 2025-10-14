<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class DepartmentRequest extends FormRequest
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
        $departmentCode = $this->route('dep_code');

        return [
            'dep_code' => [
                'required',
                'string',
                Rule::unique('departments', 'dep_code')->ignore($departmentCode, 'dep_code'),
            ],
            'dep_name' => 'required|string|max:255',
            'dep_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ];
    }
}
