<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionHeader extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'document_date' => 'datetime',
        'expected_date' => 'datetime',
        'transaction_date' => 'datetime',
    ];

    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class);
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
