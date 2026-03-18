<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'sup_image_url' => $this->getS3Url(),
            // 'sup_image_url' => $this->sup_image ? asset('storage/' . $this->sup_image) : null,
            'is_vat_supplier' => $this->is_vat_supplier,
            'vat_number'      => $this->vat_number,
            'created_by'    => $this->created_by,
            'updated_by'    => $this->updated_by,
        ];
    }

    /**
     * Get S3 URL with proper type hinting for Intelephense
     * 
     * @return string|null
     */
    private function getS3Url()
    {
        if (!$this->sup_image) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        return $disk->temporaryUrl($this->sup_image, now()->addMinutes(60));
    }
}
