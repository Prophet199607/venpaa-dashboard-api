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
        return [
            'id' => $this->id,
            'scat_code'     => $this->scat_code,
            'scat_name'     => $this->scat_name,
            'value'         => (string) $this->scat_code,
            'label'         => $this->scat_name,
            'department'    => $this->department,
            'cat_code'      => $this->cat_code,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'cat_code' => $this->category->cat_code,
                    'cat_name' => $this->category->cat_name,
                    'department' => $this->category->department,
                ];
            }),
            'department_name' => $this->getRelation('department') ? $this->getRelation('department')->dep_name : $this->department,
            'dep_data' => $this->whenLoaded('department', function () {
                $dep = $this->getRelation('department');
                return [
                    'dep_code' => $dep->dep_code,
                    'dep_name' => $dep->dep_name,
                ];
            }),
            'created_by'    => $this->created_by,
            'updated_by'    => $this->updated_by,
        ];
    }
}
