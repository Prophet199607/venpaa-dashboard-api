<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'cat_code'     => (string) $this->cat_code,
            'cat_name'     => $this->cat_name,
            'cat_image'    => $this->cat_image,
            'department'   => (string) $this->getRawOriginal('department'),
            'department_name' => $this->getRelation('department') ? $this->getRelation('department')->dep_name : (string) $this->getRawOriginal('department'),
            'dep_data' => $this->whenLoaded('department', function () {
                $dep = $this->getRelation('department');
                return [
                    'dep_code' => (string) $dep->dep_code,
                    'dep_name' => $dep->dep_name,
                ];
            }),
            // 'cat_image_url' => $this->cat_image ? asset('storage/' . $this->cat_image) : null,
            'cat_image_url' => $this->getS3Url(),
            'created_by'    => $this->created_by,
            'updated_by'    => $this->updated_by,
            'sub_categories' => $this->whenLoaded('subCategories'),
        ];
    }

    /**
     * Get S3 URL with proper type hinting for Intelephense
     * 
     * @return string|null
     */
    private function getS3Url()
    {
        if (!$this->cat_image) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        return $disk->temporaryUrl($this->cat_image, now()->addMinutes(60));
    }
}
