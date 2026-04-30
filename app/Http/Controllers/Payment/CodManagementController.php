<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CodManagement;
use Illuminate\Http\Request;

class CodManagementController extends Controller
{
    public function index()
    {
        $codData = CodManagement::orderBy('transaction_date', 'desc')->get();

        return response()->json($codData);
    }

    public function markAsReceived($id)
    {
        $cod = CodManagement::findOrFail($id);

        $cod->status = 'Received';
        $cod->received_amount = $cod->transaction_amount;
        $cod->save();

        return response()->json($cod);
    }
}
