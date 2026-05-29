<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class TempTransactionSaleHeaderRequest extends FormRequest
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
            'customer_code' => 'required|string|max:255',
            'iid' => 'nullable|string|max:255',
            'recall_type' => 'nullable|string|max:255',
            'recall_doc_no' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'p_order_no' => 'nullable|string|max:255',
            'manual_no' => 'nullable|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'sales_assistant_code' => 'nullable|string|max:255',
            'sale_type' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'subtotal' => 'nullable|numeric',
            'net_total' => 'nullable|numeric',
            'discount' => 'nullable|numeric',
            'dis_per' => 'nullable|numeric',
            'tax_per' => 'nullable|numeric',
            'delivery_charges' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'comments' => 'nullable|string|max:255',
            'payment_mode' => 'nullable|string|max:255',
            'invoice_no' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
            'invoice_amount' => 'nullable|numeric',
            'balance_amount' => 'nullable|numeric',
            'is_approved' => 'nullable|boolean',
            'approved_by' => 'nullable|string|max:255',
            'is_vat' => 'nullable|boolean',
            'vat_percent' => 'nullable|numeric',

        ];
    }
}
