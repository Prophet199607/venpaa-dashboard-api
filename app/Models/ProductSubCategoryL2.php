<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSubCategoryL2 extends Model
{
    use HasFactory;

    protected $table = 'product_sub_category_l2s';
    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class, 'prod_code', 'prod_code');
    }

    public function subCategoryL2()
    {
        return $this->belongsTo(SubCategoryL2::class, 'sub_category_l2_id', 'id');
    }
}
