<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
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
            'department'    => (string) $this->getRawOriginal('department'),
            'department_categories' => $this->whenLoaded('department', function() {
                $depRelation = $this->resource->getRelationValue('department');
                if (!$depRelation) {
                    return [];
                }
                return $depRelation->categories->map(function($cat) {
                    return [
                        'cat_code' => (string) $cat->cat_code,
                        'cat_name' => $cat->cat_name,
                        'department' => (string) $cat->department
                    ];
                });
            }),
            'category'      => (string) $this->getRawOriginal('category'),
            'sub_categories'  => $this->subCategories->map(function ($sub) {
                return [
                    'value' => (string) $sub->scat_code,
                    'label' => $sub->scat_name
                ];
            }),
            'suppliers'       => $this->suppliers->map(function ($supplier) {
                return [
                    'value' => (string) $supplier->sup_code,
                    'label' => $supplier->sup_name
                ];
            }),
            'pack_size'     => $this->pack_size,
            'purchase_price'=> $this->purchase_price,
            'selling_price' => $this->selling_price,
            'marked_price'  => $this->marked_price,
            'wholesale_price' => $this->wholesale_price,
            'alert_qty'     => $this->alert_qty,
            'width'         => $this->width,
            'height'        => $this->height,
            'depth'         => $this->depth,
            'weight'        => $this->weight,
            'barcode'       => $this->barcode,
            'prod_image'    => $this->prod_image,
            // 'prod_image_url' => $this->prod_image ? asset('storage/' . $this->prod_image) : null,
            'prod_image_url' => $this->getS3Url($this->prod_image),
            'image_urls'     => $this->whenLoaded('images', function () {
                return $this->images->map(function ($Image) {
                    // return asset('storage/' . $Image->image);
                    return $this->getS3Url($Image->image);
                });
            }),
            'description'   => $this->description,
            'status'        => $this->status ? '1' : '0',
            'unit_name' => $this->unit_name,
            'unit' => $this->whenLoaded('unit', function () {
                return [
                    'unit_type' => $this->unit->unit_type ?? null,
                ];
            }),
            'created_by'    => $this->created_by,
            'updated_by'    => $this->updated_by,
            'current_stock' => $this->current_stock ?? 0,
        ];
    }

    /**
     * Get S3 URL with proper type hinting for Intelephense
     * 
     * @param string|null $imagePath
     * @return string|null
     */
    private function getS3Url($imagePath)
    {
        if (!$imagePath) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        return $disk->temporaryUrl($imagePath, now()->addMinutes(60));
    }
}
