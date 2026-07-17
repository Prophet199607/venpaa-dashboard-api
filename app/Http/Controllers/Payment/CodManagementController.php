<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Exports\CodManagementExport;
use App\Models\CodManagement;
use App\Models\PaymentSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class CodManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = CodManagement::orderBy('transaction_date', 'desc');

        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }

        $codData = $query->get();

        return response()->json($codData);
    }

    public function markAsReceived(Request $request, $id)
    {
        $request->validate([
            'received_amount' => 'required|numeric|min:0',
        ]);

        $receivedAmount = (float) $request->input('received_amount');
        $orderNo = $request->input('orderNo');

        $cod = DB::transaction(function () use ($id, $receivedAmount, $orderNo) {
            $cod = CodManagement::findOrFail($id);

            $cod->status = 'Received';
            $cod->received_amount = $receivedAmount;
            $cod->save();

            $codDate = \Carbon\Carbon::parse($cod->transaction_date)->format('d/m/Y');

            PaymentSummary::whereIn('iid', ['COD', 'CODO'])
                ->where('ref_doc_no', $orderNo)
                ->where('transaction_date', $codDate)
                ->get()
                ->each(function ($ps) use ($receivedAmount) {
                    $ps->balance_amount = $ps->transaction_amount - $receivedAmount;
                    $ps->save();
                });

            return $cod;
        });

        return response()->json($cod);
    }

    public function markAsReturned(Request $request, $id)
    {
        $orderNo = $request->input('orderNo');

        $cod = DB::transaction(function () use ($id, $orderNo) {
            $cod = CodManagement::findOrFail($id);

            $cod->status = 'Returned';
            $cod->save();

            $codDate = \Carbon\Carbon::parse($cod->transaction_date)->format('d/m/Y');

            PaymentSummary::whereIn('iid', ['COD', 'CODO'])
                ->where('ref_doc_no', $orderNo)
                ->where('transaction_date', $codDate)
                ->get()
                ->each(function ($ps) {
                    $ps->balance_amount = $ps->transaction_amount;
                    $ps->save();
                });

            return $cod;
        });

        return response()->json($cod);
    }

    public function report(Request $request)
    {
        $query = CodManagement::orderBy('cod_management.transaction_date', 'desc');

        if ($request->filled('location')) {
            $query->where('cod_management.location', $request->location);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('cod_management.transaction_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('cod_management.transaction_date', '<=', $request->end_date);
        }

        if ($request->filled('status')) {
            $query->where('cod_management.status', $request->status);
        }

        $data = $query->leftJoin('locations', 'cod_management.location', '=', 'locations.loca_code')
            ->select('cod_management.*', 'locations.loca_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function exportReport(Request $request)
    {
        $query = CodManagement::orderBy('cod_management.transaction_date', 'desc');

        if ($request->filled('location')) {
            $query->where('cod_management.location', $request->location);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('cod_management.transaction_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('cod_management.transaction_date', '<=', $request->end_date);
        }

        if ($request->filled('status')) {
            $query->where('cod_management.status', $request->status);
        }

        $data = $query->leftJoin('locations', 'cod_management.location', '=', 'locations.loca_code')
            ->select('cod_management.*', 'locations.loca_name')
            ->get();

        return Excel::download(
            new CodManagementExport($data->toArray()),
            'COD_Management_Report.xlsx',
        );
    }
}
