<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CodManagement;
use App\Models\PaymentSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CodManagementController extends Controller
{
    public function index()
    {
        $codData = CodManagement::orderBy('transaction_date', 'desc')->get();

        return response()->json($codData);
    }

    public function markAsReceived($id)
    { 

        
        $cod = DB::transaction(function () use ($id) {
            $cod = CodManagement::findOrFail($id);

            $cod->status = 'Received';
            $cod->received_amount = $cod->transaction_amount;
            $cod->save();

            PaymentSummary::where('iid', 'COD')
                ->where('ref_doc_no', $cod->doc_no)
                ->update(['balance_amount' => 0]);

            return $cod;
        });

        return response()->json($cod);
    }
}
