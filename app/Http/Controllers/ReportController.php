<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
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

    /**
     * Get POS collection summary report (Daily Collection)
     */
    public function getPosCollectionSummaryReport(Request $request)
    {
        try {
            $locaRaw = $request->input('location', $request->input('Loca', ''));
            $location = str_pad(ltrim($locaRaw, '0'), 2, '0', STR_PAD_LEFT);

            $dateFrom = $request->input('dateFrom', '');
            $dateTo = $request->input('dateTo', '');

            DB::statement('SET @pErrorCode = 0');

            $summary = DB::select('CALL sp_PosCollectionSummaryReport(@pErrorCode, ?, ?, ?)', [
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
                'message' => 'POS collection summary report fetched successfully',
                'data' => $summary
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch POS collection summary report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Current Stock Report
     */
    public function getCurrentStockReport(Request $request)
    {
        try {
            $location = $request->input('location', $request->input('Loca', ''));
            $supplierCodes = $request->input('supplierCodes', '');
            $department = $request->input('department', '');
            $prodCodes = $request->input('prodCodes', '');
            $category = $request->input('category', '');

            DB::statement('SET @pErrorCode = 0');

            $summary = DB::select('CALL sp_CurrentStockReport(@pErrorCode, ?, ?, ?, ?, ?)', [
                $location,
                $department,
                $category,
                $supplierCodes,
                $prodCodes
            ]);

            $errorCode = DB::select('SELECT @pErrorCode as error_code')[0]->error_code;

            if ($errorCode != 0) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Stored procedure error',
                    'error_code' => $errorCode
                ], 400);
            }

            // Inject Product and Location names
            $uniqueProdCodes = array_values(array_unique(array_column($summary, 'Prod_Code')));
            $products = DB::table('products')->whereIn('prod_code', $uniqueProdCodes)->pluck('prod_name', 'prod_code');

            $uniqueLocas = array_values(array_unique(array_column($summary, 'Loca')));
            $locations = DB::table('locations')->whereIn('loca_code', $uniqueLocas)->pluck('loca_name', 'loca_code');

            foreach ($summary as &$row) {
                $row->Prod_Name = $products[$row->Prod_Code] ?? '';
                $row->Loca_Name = $locations[$row->Loca] ?? '';
            }

            return response()->json([
                'success' => true,
                'message' => 'Current stock report fetched successfully',
                'data' => $summary
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch current stock report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exportCurrentStockReport(Request $request)
    {
        try {
            $location = $request->input('location', $request->input('Loca', ''));
            $supplierCodes = $request->input('supplierCodes', '');
            $department = $request->input('department', '');
            $prodCodes = $request->input('prodCodes', '');
            $category = $request->input('category', '');

            DB::statement('SET @pErrorCode = 0');

            $summary = DB::select('CALL sp_CurrentStockReport(@pErrorCode, ?, ?, ?, ?, ?)', [
                $location,
                $department,
                $category,
                $supplierCodes,
                $prodCodes
            ]);

            $errorCode = DB::select('SELECT @pErrorCode as error_code')[0]->error_code;

            if ($errorCode != 0) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Stored procedure error',
                    'error_code' => $errorCode
                ], 400);
            }

            // Inject Product and Location names
            $uniqueProdCodes = array_values(array_unique(array_column($summary, 'Prod_Code')));
            $products = DB::table('products')->whereIn('prod_code', $uniqueProdCodes)->pluck('prod_name', 'prod_code');

            $uniqueLocas = array_values(array_unique(array_column($summary, 'Loca')));
            $locations = DB::table('locations')->whereIn('loca_code', $uniqueLocas)->pluck('loca_name', 'loca_code');

            foreach ($summary as &$row) {
                $row->Prod_Name = $products[$row->Prod_Code] ?? '';
                $row->Loca_Name = $locations[$row->Loca] ?? '';
            }

            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\CurrentStockExport($summary), 'Current_Stock_Report.xlsx');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export current stock report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
