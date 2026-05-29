<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Resources\Json\JsonResource;

class SubCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $dep = $this->resource->getRelationValue('department');

        return [
            'id' => $this->id,
            'scat_code'     => (string) $this->scat_code,
            'scat_name'     => $this->scat_name,
            'value'         => (string) $this->scat_code,
            'label'         => $this->scat_name,
            'department'    => (string) ($this->resource->getAttributes()['department'] ?? ''),
            'cat_code'      => (string) $this->cat_code,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'cat_code' => (string) $this->category->cat_code,
                    'cat_name' => $this->category->cat_name,
                    'department' => (string) ($this->category->getAttributes()['department'] ?? ''),
                ];
            }),
            'department_name' => $dep ? $dep->dep_name : (string) ($this->resource->getAttributes()['department'] ?? ''),
            'dep_data' => $this->whenLoaded('department', function () {
                $dep = $this->resource->getRelationValue('department');
                if (!$dep) {
                    return null;
                }
                return [
                    'dep_code' => (string) $dep->dep_code,
                    'dep_name' => $dep->dep_name,
                ];
            }),
            'created_by'    => $this->created_by,
            'updated_by'    => $this->updated_by,
        ];
    }
}
