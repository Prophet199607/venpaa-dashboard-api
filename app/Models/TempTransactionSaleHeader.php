<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempTransactionSaleHeader extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'document_date' => 'datetime',
        'invoice_date' => 'datetime',
    ];

    public function tempTransactionSaleDetails()
    {
        return $this->hasMany(TempTransactionSaleDetail::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location', 'loca_code');
    }
}
