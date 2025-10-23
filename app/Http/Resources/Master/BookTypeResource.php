<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Resources\Json\JsonResource;

class BookTypeResource extends JsonResource
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
            'bkt_code'     => $this->bkt_code,
            'bkt_name'     => $this->bkt_name,
            'created_by'    => $this->created_by,
            'updated_by'    => $this->updated_by,
        ];
    }
}
