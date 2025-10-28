<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class TempTransactionDetailRequest extends FormRequest
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
        return [
            'temp_transaction_header_id' => 'nullable|integer|exists:temp_transaction_headers,id',
            'doc_no' => 'required|string|max:255',
            'iid' => 'required|string|max:255',
            'prod_code' => 'required|string|max:255|exists:products,prod_code',
            'prod_name' => 'required|string|max:255',
            'qty' => 'nullable|numeric',
            'purchase_price' => 'nullable|numeric',
            'marked_price' => 'nullable|numeric',
            'selling_price' => 'nullable|numeric',
            'whole_sale' => 'nullable|numeric',
            'free_qty' => 'nullable|numeric',
            'physical_pack_qty' => 'nullable|numeric',
            'physical_unit_qty' => 'nullable|numeric',
            'pack_qty' => 'nullable|numeric',
            'total_qty' => 'nullable|numeric',
            'physical_qty' => 'nullable|numeric',
            'pack_size' => 'nullable|numeric',
            'discount' => 'nullable|numeric',
            'line_wise_discount_value' => 'nullable|numeric',
            'dis_per' => 'nullable|numeric',
            'amount' => 'nullable|numeric',
        ];
    }
}
