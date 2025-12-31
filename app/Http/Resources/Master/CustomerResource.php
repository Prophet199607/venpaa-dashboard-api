<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'customer_code'   => $this->customer_code,
            'customer_name'   => $this->customer_name,
            'mobile'          => $this->mobile,
            'nic'             => $this->nic,
            'dob'             => $this->dob,
        ];
    }
}
