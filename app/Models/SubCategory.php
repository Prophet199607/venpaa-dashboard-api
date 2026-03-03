<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubCategory extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'cat_code', 'cat_code');
    }

    public function department_relation(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department', 'dep_code');
    }

    public function subCategoryL2s(): HasMany
    {
        return $this->hasMany(SubCategoryL2::class, 'scat_code', 'scat_code');
    }

    protected static function booted()
    {
        // Update DocNumber after successful creation
        static::created(function ($subCategory) {
            $docNumber = DocNumber::where('type', 'SubCategory')->first();
            if ($docNumber) {
                $docNumber->incrementLastId();
            }
        });
    }
}
