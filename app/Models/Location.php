<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected static function booted()
    {
        // Update DocNumber after successful creation
        static::created(function ($location) {
            DocNumber::where('type', 'Location')->first()?->incrementLastId();
        });
    }
}