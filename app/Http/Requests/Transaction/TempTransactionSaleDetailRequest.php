<?php

namespace App\Http\Requests\Transaction;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class TempTransactionSaleDetailRequest extends FormRequest
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
        $rules = [
            'temp_transaction_sale_header_id' => 'required|integer',
            'doc_no' => 'required|string|max:255',
            'line_no' => 'required|integer',
            'iid' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'prod_code' => 'required|string|max:255',
            'prod_name' => 'required|string|max:255',
            'purchase_price' => 'nullable|numeric',
            'marked_price' => 'nullable|numeric',
            'selling_price' => 'nullable|numeric',
            'whole_sale' => 'nullable|numeric',
            'total_qty' => 'nullable|numeric',
            'pack_size' => 'nullable|numeric',
            'discount' => 'nullable|numeric',
            'line_wise_discount_value' => 'nullable|string|max:255',
            'dis_per' => 'nullable|numeric',
            'amount' => 'nullable|numeric',
        ];

        $product = Product::where('prod_code', $this->prod_code)->with('unit')->first();
        $unitType = $product->unit->unit_type ?? null;

        if ($unitType === 'WHOLE') {
            $quantityRules = 'nullable|integer';
        } elseif ($unitType === 'DEC') {
            $quantityRules = 'nullable|numeric|regex:/^-?\d+(\.\d{1,3})?$/';
        } else {
            $quantityRules = 'nullable|numeric';
        }

        $rules['pack_qty'] = $quantityRules;
        $rules['unit_qty'] = $quantityRules;
        $rules['free_qty'] = $quantityRules;

        return $rules;
    }
}
