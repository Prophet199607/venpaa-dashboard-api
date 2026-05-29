<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AuthorResource extends JsonResource
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
            'auth_code'             => $this->auth_code,
            'auth_name'             => $this->auth_name,
            'auth_name_other_language'       => $this->auth_name_other_language,
            'description'           => $this->description,
            'auth_image'            => $this->auth_image,
            'auth_image_url'        => $this->getS3Url(),
            // 'auth_image_url'        => $this->auth_image ? asset('storage/' . $this->auth_image) : null,
            'created_by'            => $this->created_by,
            'updated_by'            => $this->updated_by,
        ];
    }

    /**
     * Get S3 URL with proper type hinting for Intelephense
     * 
     * @return string|null
     */
    private function getS3Url()
    {
        if (!$this->auth_image) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        return $disk->temporaryUrl($this->auth_image, now()->addMinutes(60));
    }
}
