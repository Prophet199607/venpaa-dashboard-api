<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Author extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected static function booted()
    {
        // Update DocNumber after successful creation
        static::created(function ($author) {

            $docNumber = DocNumber::where('type', 'Author')->first();
            if ($docNumber) {
                $docNumber->incrementLastId();
            }
        });
    }
}
