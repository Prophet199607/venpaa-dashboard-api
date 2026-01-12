<?php

namespace App\Http\Resources\Transaction;

use Illuminate\Http\Resources\Json\JsonResource;

class TempTransactionSaleHeaderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'location' => $this->location,
            'doc_no' => $this->doc_no,
            'document_date' => $this->document_date,
            'customer_code' => $this->customer_code,
            'iid' => $this->iid,
            'recall_type' => $this->recall_type,
            'recall_doc_no' => $this->recall_doc_no,
            'address' => $this->address,
            'p_order_no' => $this->p_order_no,
            'manual_no' => $this->manual_no,
            'customer_name' => $this->customer_name,
            'sales_assistant_code' => $this->sales_assistant_code,
            'sale_type' => $this->sale_type,
            'payment_Method' => $this->payment_Method,
            'type' => $this->type,
            'subtotal' => $this->subtotal,
            'net_total' => $this->net_total,
            'discount' => $this->discount,
            'dis_per' => $this->dis_per,
            'tax_per' => $this->tax_per,
            'delivery_charges' => $this->delivery_charges,
            'tax' => $this->tax,
            'comments' => $this->comments,
            'payment_mode' => $this->payment_mode,
            'invoice_no' => $this->invoice_no,
            'invoice_date' => $this->invoice_date,
            'invoice_amount' => $this->invoice_amount,
            'balance_amount' => $this->balance_amount,
            'is_approved' => $this->is_approved,
            'approved_by' => $this->approved_by,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
