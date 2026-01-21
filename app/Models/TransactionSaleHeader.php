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

    public function transactionSaleDetails()
    {
        return $this->hasMany(TransactionSaleDetail::class);
    }
}
