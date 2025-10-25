<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected static function booted()
    {

        // Update DocNumber after successful creation
        static::created(function ($supplier) {
            $docNumber = DocNumber::where('type', 'Supplier')->first();
            if ($docNumber) {
                $docNumber->incrementLastId();
            }
        });
    }
}
