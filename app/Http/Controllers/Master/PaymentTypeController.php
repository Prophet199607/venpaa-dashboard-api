<?php

namespace App\Http\Controllers\Master;

use App\Models\PaymentType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Master\PaymentTypeResource;

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
}
