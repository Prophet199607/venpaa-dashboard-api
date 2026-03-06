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

    /**
     * Get POS sales summary report
     */
    public function getPosSalesSummaryReport(Request $request)
    {
        try {
            $locaRaw = $request->input('location', $request->input('Loca', ''));
            $location = str_pad(ltrim($locaRaw, '0'), 2, '0', STR_PAD_LEFT);
            
            $dateFrom = $request->input('dateFrom', '');
            $dateTo = $request->input('dateTo', '');

            DB::statement('SET @pErrorCode = 0');            
            $summary = DB::select('CALL sp_PosSalesSummaryReport(@pErrorCode, ?, ?, ?)', [
                $location,
                $dateFrom,
                $dateTo
            ]);

            $errorCode = DB::select('SELECT @pErrorCode as error_code')[0]->error_code;

            if ($errorCode != 0) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Stored procedure error',
                    'error_code' => $errorCode
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'POS sales summary report fetched successfully',
                'data' => $summary
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch POS sales summary report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
