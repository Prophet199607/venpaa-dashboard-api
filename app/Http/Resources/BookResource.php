<?php

namespace App\Http\Resources;

use App\Http\Resources\SubCategoryResource;
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
            'book_type'     => $this->book_type,
            'department'    => $this->department,
            'category'      => $this->category,
            'sub_category'  => new SubCategoryResource($this->whenLoaded('subCategory')),
            'publisher'     => $this->publisher,
            'supplier'      => $this->supplier,
            'author'        => $this->author,
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
            'image_urls'    => $this->whenLoaded('images', function () {
                return $this->images->map(function ($bookImage) {
                    return asset('storage/' . $bookImage->image);
                });
            }),
            'description'   => $this->description,
            'created_by'    => $this->created_by,
            'updated_by'    => $this->updated_by,
        ];
    }
}