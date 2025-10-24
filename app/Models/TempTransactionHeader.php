<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempTransactionHeader extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function TempTransactionDetails()
    {
        return $this->hasMany(TempTransactionDetail::class);
    }
}
