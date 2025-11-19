<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemReqTransHeader extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'document_date' => 'datetime',
        'expected_date' => 'datetime',
        'transaction_date' => 'datetime',
    ];

    public function itemReqTransDetails()
    {
        return $this->hasMany(ItemReqTransDetail::class, 'item_transaction_header_id', 'id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_code', 'sup_code');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location', 'loca_code');
    }

    public function deliveryLocation()
    {
        return $this->belongsTo(Location::class, 'delivery_location', 'loca_code');
    }
}
