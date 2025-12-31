<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected static function booted()
    {
        // Update DocNumber after successful creation
        static::created(function ($customer) {
            $docNumber = DocNumber::where('type', 'Customer')->first();
            if ($docNumber) {
                $docNumber->incrementLastId();
            }
        });
    }
}
