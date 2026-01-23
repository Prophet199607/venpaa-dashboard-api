<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosTransactionApi extends Model
{
    use HasFactory;

    protected $table = 'pos_transaction_api';
    protected $primaryKey = 'Id_No';
    public $timestamps = false;

    protected $guarded = [];
}
