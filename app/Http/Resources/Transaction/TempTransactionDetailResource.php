<?php

namespace App\Http\Resources\Transaction;

use Illuminate\Http\Resources\Json\JsonResource;

class TempTransactionDetailResource extends JsonResource
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
            'temp_transaction_header_id' => $this->temp_transaction_header_id,
            'doc_no' => $this->doc_no,
            'line_no' => $this->line_no,
            'iid' => $this->iid,
            'prod_code' => $this->prod_code,
            'prod_name' => $this->prod_name,
            'purchase_price' => $this->purchase_price,
            'marked_price' => $this->marked_price,
            'selling_price' => $this->selling_price,
            'whole_sale' => $this->whole_sale,
            'wholesale_price' => $this->whole_sale,
            'pack_qty' => $this->pack_qty,
            'unit_qty' => $this->unit_qty,
            'free_qty' => $this->free_qty,
            'physical_pack_qty' => $this->physical_pack_qty,
            'physical_unit_qty' => $this->physical_unit_qty,
            'total_qty' => $this->total_qty,
            'physical_total_qty' => $this->physical_total_qty,
            'pack_size' => $this->pack_size,
            'discount' => $this->discount,
            'line_wise_discount_value' => $this->line_wise_discount_value,
            'dis_per' => $this->dis_per,
            'amount' => $this->amount,
            'unit_name' => $this->product->unit_name,
            'unit' => [
                'unit_type' => $this->product->unit->unit_type ?? null,
            ],
            'unconfirmed_price' => $this->product ? $this->product->unconfirm_price : false,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
