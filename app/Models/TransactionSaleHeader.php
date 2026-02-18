<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionSaleHeader extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'document_date' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location', 'loca_code');
    }

    public function deliveryLocation()
    {
        return $this->belongsTo(Location::class, 'delivery_location', 'loca_code');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_code', 'customer_code');
    }

    public function transactionSaleDetails()
    {
        return $this->hasMany(TransactionSaleDetail::class);
    }
}
