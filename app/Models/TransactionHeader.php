<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionHeader extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class);
    }
}
