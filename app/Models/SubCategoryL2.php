<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubCategoryL2 extends Model
{
    use HasFactory;

    protected $table = 'sub_category_l2s';
    protected $guarded = [];

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class, 'scat_code', 'scat_code');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'cat_code', 'cat_code');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department', 'dep_code');
    }

    protected static function booted()
    {
        // Update DocNumber after successful creation
        static::created(function ($subCategoryL2) {
            $docNumber = DocNumber::where('type', 'SubCategoryL2')->first();
            if ($docNumber) {
                $docNumber->incrementLastId();
            }
        });
    }
}
