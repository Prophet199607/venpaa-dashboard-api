<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department', 'dep_code');
    }

    public function subCategories(): HasMany
    {
        return $this->hasMany(SubCategory::class, 'cat_code', 'cat_code');
    }
}