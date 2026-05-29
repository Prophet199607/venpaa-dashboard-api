<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductImage;
use App\Models\PriceLevel;

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

    public function subCategories()
    {
        return $this->belongsToMany(SubCategory::class, 'product_sub_categories', 'prod_code', 'sub_category_id', 'prod_code', 'id');
    }

    public function subCategoryL2s()
    {
        return $this->belongsToMany(SubCategoryL2::class, 'product_sub_category_l2s', 'prod_code', 'sub_category_l2_id', 'prod_code', 'id');
    }

    public function languageRelation()
    {
        return $this->belongsTo(Language::class, 'language', 'lang_code');
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

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'product_suppliers', 'prod_code', 'supplier_id', 'prod_code', 'id')
                    ->withTimestamps();
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'prod_code', 'prod_code');
    }

    public function price_levels()
    {
        return $this->hasMany(PriceLevel::class, 'prod_code', 'prod_code');
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
