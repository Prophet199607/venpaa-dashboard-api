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
}
