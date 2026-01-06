<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Resources\Json\JsonResource;

class PaidPaymentSummaryResource extends JsonResource
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
            'temp_doc_no' => $this->temp_doc_no,
            'org_doc_no' => $this->org_doc_no,
            'doc_no' => $this->doc_no,
            'payment_mode' => $this->payment_mode,
            'bank_name' => $this->bank_name,
            'cheque_no' => $this->cheque_no,
            'cheque_date' => $this->cheque_date,
            'branch' => $this->branch,
            'amount' => (float) $this->amount,
            'location' => $this->location,
            'iid' => $this->iid,
            'acc_code' => $this->acc_code,
            'document_date' => $this->document_date,
            'transaction_date' => $this->transaction_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

