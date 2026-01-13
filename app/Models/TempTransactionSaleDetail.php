<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempTransactionSaleDetail extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function TempTransactionSaleHeader()
    {
        return $this->belongsTo(TempTransactionSaleHeader::class, "doc_no");
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'prod_code', 'prod_code');
    }
}
