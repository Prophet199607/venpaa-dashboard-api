<?php

namespace App\Http\Controllers\Payment;

use App\Models\DocNumber;
use Illuminate\Http\Request;
use App\Models\PaymentSummary;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentVoucherController extends Controller
{
    use HasFactory;
    protected $guarded = [];

    public function getPmtNumber(Request $request)
    {
        $request->validate([
            'loca' => 'required|string|max:5',
        ]);

        $loca = $request->loca;

        $doc = DocNumber::firstOrCreate(
            ['type' => 'Payment'],
            [
                'prefix' => 'PMT',
                'last_id' => 0,
            ]
        );

        $number = $doc->prefix
            . $loca
            . str_pad($doc->last_id + 1, 8, '0', STR_PAD_LEFT);

        return response()->json([
            'success' => true,
            'message' => 'Code generated successfully',
            'code' => $number,
        ]);
    }

    public function getPendingPaymentsVoucher($supplier_code, $loca_code, $iid)
    {
        $payments = PaymentSummary::where('acc_type', 'supplier')
            ->where('acc_code', $supplier_code)
            ->where('location', $loca_code)
            ->where('iid', $iid)
            ->where('balance_amount', '>', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    public function getTotalOutstandingAdvances($supplier_code, $loca_code)
    {
        $total = PaymentSummary::where('iid', 'SADV')
            ->where('acc_type', 'supplier')
            ->where('acc_code', $supplier_code)
            ->where('location', $loca_code)
            ->where('balance_amount', '>', 0)
            ->sum('balance_amount');

        return response()->json([
            'success' => true,
            'total' => $total
        ]);
    }
}
