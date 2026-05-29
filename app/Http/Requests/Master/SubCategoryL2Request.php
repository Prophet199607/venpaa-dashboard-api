<?php

namespace App\Http\Requests\Master;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class SubCategoryL2Request extends FormRequest
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
        $scatL2Code = $this->route('scat_l2_code');

        return [
            'scat_l2_code' => [
                'required',
                'string',
                Rule::unique('sub_category_l2s', 'scat_l2_code')->ignore($scatL2Code, 'scat_l2_code'),
            ],
            'scat_l2_name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'cat_code' => 'required|string|max:255',
            'scat_code' => 'required|string|max:255',
            'status' => 'nullable|integer',
        ];
    }
}
