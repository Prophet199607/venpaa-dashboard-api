<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
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
            'book_code'     => $this->book_code,
            'title'         => $this->title,
            'isbn'          => $this->isbn,
            'publish_year'  => $this->publish_year,
            'book_type'     => $this->whenLoaded('bookType'),
            'department'    => $this->whenLoaded('department'),
            'category'      => $this->whenLoaded('category'),
            'sub_category'  => $this->whenLoaded('subCategory'),
            'publisher'     => $this->whenLoaded('publisher'),
            'supplier'      => $this->whenLoaded('supplier'),
            'author'        => $this->whenLoaded('author'),
            'pack_size'     => $this->pack_size,
            'alert_qty'     => $this->alert_qty,
            'width'         => $this->width,
            'height'        => $this->height,
            'depth'         => $this->depth,
            'weight'        => $this->weight,
            'pages'         => $this->pages,
            'barcode'       => $this->barcode,
            'language'      => $this->language,
            'cover_image'   => $this->cover_image,
            'cover_image_url' => $this->cover_image ? asset('storage/' . $this->cover_image) : null,
            'images'        => $this->whenLoaded('images'),
            'description'   => $this->description,
            'created_by'    => $this->created_by,
            'updated_by'    => $this->updated_by,
        ];
    }
}