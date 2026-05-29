<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class PaymentVoucherRequest extends FormRequest
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
            'receipt.doc_no' => 'required|string',
            'receipt.location' => 'required|string',
            'receipt.date' => 'required|date',
            'receipt.over_payment' => 'numeric',

            'supplier.supplier_code' => 'required|string',

            'payments' => 'required|array|min:1',
            'payments.*.mode' => 'required|string',
            'payments.*.amount' => 'required|numeric',
            'payments.*.bank' => 'nullable|string',
            'payments.*.branch' => 'nullable|string',
            'payments.*.chequeNo' => 'nullable|string',
            'payments.*.chequeDate' => 'nullable|date',
            'payments.*.cardType' => 'nullable|string',
            'payments.*.cardNumber' => 'nullable|string',

            'allocations' => 'required|array',
            'allocations.*.doc_no' => 'required|string',
            'allocations.*.transaction_amount' => 'required|numeric',
            'allocations.*.balance_amount' => 'required|numeric',
            'allocations.*.paid_amount' => 'required|numeric',

            'setoff.selectedDocs' => 'nullable|array',
            'setoff.selectedDocs.*.doc_no' => 'required_with:setoff.selectedDocs|string',
            'setoff.selectedDocs.*.transaction_amount' => 'required_with:setoff.selectedDocs|numeric',
            'setoff.selectedDocs.*.balance_amount' => 'required_with:setoff.selectedDocs|numeric',
            'setoff.selectedDocs.*.paid_amount' => 'required_with:setoff.selectedDocs|numeric',
        ];
    }
}
