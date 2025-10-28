<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempTransactionHeader extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'document_date' => 'datetime',
        'expected_date' => 'datetime',
        'transaction_date' => 'datetime',
    ];

    public function TempTransactionDetails()
    {
        return $this->hasMany(TempTransactionDetail::class);
    }
}
