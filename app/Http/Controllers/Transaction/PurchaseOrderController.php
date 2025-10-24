<?php

namespace App\Http\Controllers\Transaction;

use App\Models\DocNumber;
use App\Models\PurchaseOrder;
use App\Http\Controllers\Controller;

class PurchaseOrderController extends Controller
{
    public function getTempPoNumber($loca_code)
    {
        try {
            $docCode = DocNumber::generate('TempPO', 'PO', 8, $loca_code);

            return response()->json([
                'success' => true,
                'message' => 'Code generated successfully',
                'code' => $docCode
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate code',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
