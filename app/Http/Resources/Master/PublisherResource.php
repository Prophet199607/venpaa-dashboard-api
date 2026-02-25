<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PublisherResource extends JsonResource
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
            'pub_code'      => $this->pub_code,
            'pub_name'      => $this->pub_name,
            'website'       => $this->website,
            'contact'       => $this->contact,
            'email'         => $this->email,
            'description'   => $this->description,
            'pub_image'     => $this->pub_image,
            'pub_image_url' => $this->getS3Url(),
            // 'pub_image_url' => $this->pub_image ? asset('storage/' . $this->pub_image) : null,
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
        if (!$this->pub_image) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        return $disk->temporaryUrl($this->pub_image, now()->addMinutes(60));
    }
}
