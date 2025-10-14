<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class PublisherRequest extends FormRequest
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
        $publisherCode = $this->route('pub_code');

        return [
            'pub_code' => [
                'required',
                'string',
                Rule::unique('publishers', 'pub_code')->ignore($publisherCode, 'pub_code'),
            ],
            'pub_name' => 'required|string',
            'pub_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'website' => 'nullable|string',
            'contact' => 'nullable|string',
            'email' => 'nullable|string|email',
            'description' => 'nullable|string',
        ];
    }
}