<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'cat_code'     => $this->cat_code,
            'cat_name'     => $this->cat_name,
            'cat_slug'     => $this->cat_slug,
            'cat_image'    => $this->cat_image,
            'department'   => $this->department,
            'cat_image_url' => $this->cat_image ? asset('storage/' . $this->cat_image) : null,
            'created_by'    => $this->created_by,
            'updated_by'    => $this->updated_by,
            'sub_categories' => $this->whenLoaded('subCategories'),
        ];
    }
}