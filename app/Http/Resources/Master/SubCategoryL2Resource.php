<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Resources\Json\JsonResource;

class SubCategoryL2Resource extends JsonResource
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
            'scat_l2_code' => (string) $this->scat_l2_code,
            'scat_l2_name' => $this->scat_l2_name,
            'value'        => (string) $this->scat_l2_code,
            'label'        => $this->scat_l2_name,
            'department' => (string) ($this->resource->getAttributes()['department'] ?? ''),
            'cat_code' => (string) $this->cat_code,
            'scat_code' => (string) $this->scat_code,
            'sub_category' => $this->whenLoaded('subCategory', function () {
                return [
                    'id' => $this->subCategory->id,
                    'scat_code' => (string) $this->subCategory->scat_code,
                    'scat_name' => $this->subCategory->scat_name,
                ];
            }),
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
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ];
    }
}
