<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookType extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected static function booted()
    {
        // Update DocNumber after successful creation
        static::created(function ($bookType) {
            $docNumber = DocNumber::where('type', 'BookType')->first();
            if ($docNumber) {
                $docNumber->incrementLastId();
            }
        });
    }
}
