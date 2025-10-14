<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function author()
    {
        return $this->belongsTo(Author::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function bookType()
    {
        return $this->belongsTo(BookType::class);
    }

    public function publisher()
    {
        return $this->belongsTo(Publisher::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    protected static function booted()
    {
        // Update DocNumber after successful creation
        static::created(function ($book) {
            DocNumber::where('type', 'Book')->first()?->incrementLastId();
        });
    }
}