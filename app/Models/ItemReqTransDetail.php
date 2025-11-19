<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemReqTransDetail extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function itemReqTransHeader()
    {
        return $this->belongsTo(ItemReqTransHeader::class, 'item_transaction_header_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'prod_code', 'prod_code');
    }
}
