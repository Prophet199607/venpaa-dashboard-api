<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\BookImage;

class Book extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function author()
    {
        return $this->belongsTo(Author::class, 'author', 'auth_code');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category', 'cat_code');
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class, 'sub_category', 'scat_code');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department', 'dep_code');
    }

    public function bookType()
    {
        return $this->belongsTo(BookType::class, 'book_type', 'bkt_code');
    }

    public function publisher()
    {
        return $this->belongsTo(Publisher::class, 'publisher', 'pub_code');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier', 'sup_code');
    }

    public function images()
    {
        return $this->hasMany(BookImage::class, 'book_code', 'book_code');
    }

    protected static function booted()
    {
        // Update DocNumber after successful creation
        static::created(function ($book) {
            DocNumber::where('type', 'Book')->first()?->incrementLastId();
        });
    }
}
