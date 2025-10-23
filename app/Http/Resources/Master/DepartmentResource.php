<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
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
            'dep_code'     => $this->dep_code,
            'dep_name'     => $this->dep_name,
            'dep_image'    => $this->dep_image,
            'dep_image_url' => $this->dep_image ? asset('storage/' . $this->dep_image) : null,
            'created_by'    => $this->created_by,
            'updated_by'    => $this->updated_by,
        ];
    }
}
