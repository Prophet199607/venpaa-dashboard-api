<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductImage;

class Product extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function authorDetails()
    {
        return $this->belongsTo(Author::class, 'author', 'auth_code');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_name', 'unit_name');
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

    public function supplierDetails()
    {
        return $this->belongsTo(Supplier::class, 'supplier', 'sup_code');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'prod_code', 'prod_code');
    }

    protected static function booted()
    {
        // Update DocNumber after successful creation
        static::created(function ($product) {
            DocNumber::where('type', 'Product')->first()?->incrementLastId();
        });
    }
}
