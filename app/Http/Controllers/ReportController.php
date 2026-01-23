<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Get stock summary using Stored Procedure
     */
    public function getStockSummary(Request $request)
    {
        try {
            $locationCode = $request->input('location_code', '');

            // Calling the Stored Procedure
            $summary = DB::select('CALL GetStockSummary(?)', [$locationCode]);

            return response()->json([
                'success' => true,
                'message' => 'Stock summary fetched successfully',
                'data' => $summary
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stock summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
