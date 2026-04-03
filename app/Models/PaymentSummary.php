<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSummary extends Model
{
    use HasFactory;

    /**
     * Get the customer associated with the payment summary.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'acc_code', 'customer_code');
    }
}
