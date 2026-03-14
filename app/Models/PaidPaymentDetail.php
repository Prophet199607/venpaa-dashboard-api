<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaidPaymentDetail extends Model
{
    use HasFactory;

    /**
     * Allow mass assignment for dynamic payloads built in controllers.
     */
    protected $guarded = [];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'acc_code', 'sup_code');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'acc_code', 'customer_code');
    }
}
