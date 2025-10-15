<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class BookTypeRequest extends FormRequest
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
        $bookTypeCode = $this->route('bkt_code');
        return [
            'bkt_code' => [
                'required',
                'string',
                Rule::unique('book_types', 'bkt_code')->ignore($bookTypeCode, 'bkt_code'),
            ],
            'bkt_name' => 'required|string|max:255',
            'status' => 'nullable|integer',
        ];
    }
}