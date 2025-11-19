<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class ItemReqTransHeaderRequest extends FormRequest
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
            'location' => 'required|string|max:255',
            'doc_no' => 'required|string|max:255',
            'document_date' => 'nullable|date',
            'expected_date' => 'nullable|date',
            'transaction_date' => 'nullable|string|max:255',
            'grn_date' => 'nullable|date',
            'iid' => 'required|string|max:255',
            'supplier_code' => 'nullable|string|max:255',
            'delivery_address' => 'nullable|string',
            'delivery_location' => 'nullable|string|max:255',
            'ref_number' => 'nullable|string|max:255',
            'remarks_ref' => 'nullable|string',
            'grn_remarks' => 'nullable|string',
            'srn_remarks' => 'nullable|string',
            'subtotal' => 'nullable|numeric',
            'net_total' => 'nullable|numeric',
            'discount' => 'nullable|numeric',
            'dis_per' => 'nullable|numeric',
            'tax_per' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'grn_no' => 'nullable|string|max:255',
            'payment_mode' => 'nullable|string|max:255',
            'invoice_no' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
            'invoice_amount' => 'nullable|numeric',
        ];
    }
}
