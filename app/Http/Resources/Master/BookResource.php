<?php

namespace App\Http\Resources\Master;

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
            'prod_code'     => $this->prod_code,
            'prod_name'     => $this->prod_name,
            'short_description'     => $this->short_description,
            'department'    => $this->department,
            'category'      => $this->category,
            'sub_category'  => new SubCategoryResource($this->whenLoaded('subCategory')),
            'supplier'      => $this->supplier,
            'pack_size'     => $this->pack_size,
            'purchase_price'=> $this->purchase_price,
            'selling_price' => $this->selling_price,
            'marked_price'  => $this->marked_price,
            'wholesale_price' => $this->wholesale_price,
            'book_type'     => $this->book_type,
            'publisher'     => $this->publisher,
            'authors'       => $this->whenLoaded('authors', function () {
                return $this->authors->map(function ($author) {
                    return [
                        'value' => $author->auth_code,
                        'label' => $author->auth_name
                    ];
                });
            }),
            'isbn'          => $this->isbn,
            'publish_year'  => $this->publish_year,
            'alert_qty'     => $this->alert_qty,
            'width'         => $this->width,
            'height'        => $this->height,
            'depth'         => $this->depth,
            'weight'        => $this->weight,
            'pages'         => $this->pages,
            'barcode'       => $this->barcode,
            'language'      => $this->language,
            'prod_image'    => $this->prod_image,
            'prod_image_url' => $this->prod_image ? asset('storage/' . $this->prod_image) : null,
            'image_urls'     => $this->whenLoaded('images', function () {
                return $this->images->map(function ($Image) {
                    return asset('storage/' . $Image->image);
                });
            }),
            'description'   => $this->description,
            'status'        => $this->status ? '1' : '0',
            'unit_name'     => $this->unit_name,
            'created_by'    => $this->created_by,
            'updated_by'    => $this->updated_by,
        ];
    }
}
