<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
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
            'id'            => $this->id,
            'sup_code'      => $this->sup_code,
            'sup_name'      => $this->sup_name,
            'company'       => $this->company,
            'address'       => $this->address,
            'mobile'        => $this->mobile,
            'telephone'     => $this->telephone,
            'email'         => $this->email,
            'description'   => $this->description,
            'sup_image'     => $this->sup_image,
            'sup_image_url' => $this->sup_image ? asset('storage/' . $this->sup_image) : null,
            'created_by'    => $this->created_by,
            'updated_by'    => $this->updated_by,
        ];
    }
}
