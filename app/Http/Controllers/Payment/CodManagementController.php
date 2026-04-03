<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\PaymentSummary;
use Illuminate\Http\Request;

class CodManagementController extends Controller
{
    public function index()
    {
        $pendingCod = PaymentSummary::with('customer')
            ->where('iid', 'COD')
            ->where('balance_amount', '>', 0)
            ->get();

        return response()->json($pendingCod);
    }
}
