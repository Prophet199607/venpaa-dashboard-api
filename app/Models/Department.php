<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'department', 'dep_code');
    }

    protected static function booted()
    {
        // Update DocNumber after successful creation
        static::created(function ($department) {
            $docNumber = DocNumber::where('type', 'Department')->first();
            if ($docNumber) {
                $docNumber->incrementLastId();
            }
        });
    }
}
