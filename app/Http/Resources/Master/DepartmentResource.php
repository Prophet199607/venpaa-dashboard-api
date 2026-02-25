<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            // 'dep_image_url' => $this->dep_image ? asset('storage/' . $this->dep_image) : null,
            'dep_image_url' => $this->getS3Url(),
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
        if (!$this->dep_image) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        return $disk->temporaryUrl($this->dep_image, now()->addMinutes(60));
    }
}
