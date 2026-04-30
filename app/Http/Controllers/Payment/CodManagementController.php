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
}
