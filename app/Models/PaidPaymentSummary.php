<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaidPaymentSummary extends Model
{
    use HasFactory;

    /**
     * Allow mass assignment for dynamic payloads built in controllers.
     */
    protected $guarded = [];
}
