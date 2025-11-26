<?php

namespace App\Http\Resources\Transaction;

use Illuminate\Http\Resources\Json\JsonResource;

class TempTransactionHeaderResource extends JsonResource
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
            'expected_date' => $this->expected_date,
            'transaction_date' => $this->transaction_date,
            'grn_date' => $this->grn_date,
            'iid' => $this->iid,
            'supplier_code' => $this->supplier_code,
            'delivery_address' => $this->delivery_address,
            'delivery_location' => $this->delivery_location,
            'ref_number' => $this->ref_number,
            'remarks_ref' => $this->remarks_ref,
            'grn_remarks' => $this->grn_remarks,
            'srn_remarks' => $this->srn_remarks,
            'subtotal' => $this->subtotal,
            'net_total' => $this->net_total,
            'discount' => $this->discount,
            'dis_per' => $this->dis_per,
            'tax_per' => $this->tax_per,
            'tax' => $this->tax,
            'recall_doc_no' => $this->recall_doc_no,
            'payment_mode' => $this->payment_mode,
            'invoice_no' => $this->invoice_no,
            'invoice_date' => $this->invoice_date,
            'invoice_amount' => $this->invoice_amount,
            'is_approved' => $this->is_approved,
            'approved_by' => $this->approved_by,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
