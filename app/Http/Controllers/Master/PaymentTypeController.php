<?php

namespace App\Http\Controllers\Master;

use App\Models\PaymentType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Master\PaymentTypeResource;
use App\Models\PaymentSummary;

class PaymentTypeController extends Controller
{
    public function index()
    {
        try {
            $paymentTypes = PaymentType::where('status', 1)->where('mandatory', 1)->get();
            return response()->json([
                'success' => true,
                'message' => 'Payment types fetched successfully',
                'data' => PaymentTypeResource::collection($paymentTypes)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function paymentTypesInvoice()
    {
        try {
            $paymentTypes = PaymentType::where('status', 1)->get();
            return response()->json([
                'success' => true,
                'message' => 'Payment types fetched successfully',
                'data' => PaymentTypeResource::collection($paymentTypes)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment types',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function loadAllSetoffPayments($customer_code, $location)
    {
        try {
            // Fetch all advance payments for this customer at this location
            $advances = PaymentSummary::where('acc_code', $customer_code)
                ->where('location', $location)
                ->whereIn('iid', ['CADV', 'SADV'])
                ->where('balance_amount', '>', 0)
                ->orderBy('transaction_date', 'desc')
                ->get();

            // Fetch overpayments for this customer at this location
            $over_pay = PaymentSummary::where('acc_code', $customer_code)
                ->where('location', $location)
                ->whereIn('iid', ['OVPMT', 'OVREC'])
                ->where('balance_amount', '>', 0)
                ->orderBy('transaction_date', 'desc')
                ->get();

            $return = PaymentSummary::where('acc_code', $customer_code)
                ->where('location', $location)
                ->whereIn('iid', ['SRN', 'CUR'])
                ->where('balance_amount', '>', 0)
                ->orderBy('transaction_date', 'desc')
                ->get();

            return response()->json([
                'message' => 'Advances and overpayments loaded successfully',
                'advances' => $advances,
                'over_pay' => $over_pay,
                'return' => $return
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load advances',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
