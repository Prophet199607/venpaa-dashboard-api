<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class AuthorRequest extends FormRequest
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
        $authorCode = $this->route('auth_code');

        return [
            'auth_code' => [
                'required',
                'string',
                Rule::unique('authors', 'auth_code')->ignore($authorCode, 'auth_code'),
            ],

            'auth_name' => 'required|string',
            'auth_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'auth_name_tamil' => 'nullable|string',
            'description' => 'nullable|string',
        ];
    }
}
