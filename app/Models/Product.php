<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductImage;

class Product extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function authors()
    {
        return $this->belongsToMany(Author::class, 'product_authors', 'prod_code', 'author_id', 'prod_code', 'id')
                    ->withTimestamps();
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
            $docNumber = DocNumber::where('type', 'Product')->first();
            if ($docNumber) {
                $docNumber->incrementLastId();
            }
        });
    }
}
