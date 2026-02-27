<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Models\PosTransactionApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SalesController extends Controller
{
    /**
     * Insert POS Sales transactions
     */
    public function InsertPosSales(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Details' => 'required|array|min:1',
            'Details.*.R_No' => 'required|string|max:1000|distinct',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $details = $request->input('Details');

        try {

            $rNos = collect($details)->pluck('R_No')->unique()->toArray();

            $existingRNos = PosTransactionApi::whereIn('R_No', $rNos)
                ->pluck('R_No')
                ->toArray();

            DB::beginTransaction();

            $records = [];
            $processedRNos = [];

            foreach ($details as $item) {

                // Skip if exists in DB
                if (in_array($item['R_No'], $existingRNos)) {
                    continue;
                }

                // Skip if already processed in this loop
                if (in_array($item['R_No'], $processedRNos)) {
                    continue;
                }

                $records[] = $this->storeRecord($item);

                $processedRNos[] = $item['R_No'];
            }

            DB::commit();

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'POS transactions processed successfully',
                'data' => $records,
                'saved_count' => count($records)
            ], 200);

        } catch (Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to process transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Map request keys to DB columns and store the record.
     */
    private function storeRecord(array $data)
    {
        $mapped = [
            'BatchCode'       => $data['Batch_Code'] ?? null,
            'Loca'            => $data['Loca'] ?? null,
            'Iid'             => $data['Iid'] ?? null,
            'Item_Code'       => $data['Item_Code'] ?? null,
            'Item_Descrip'    => $data['Item_Descrip'] ?? null,
            'Unit_Price'      => $data['Unit_Price'] ?? null,
            'Cost_Price'      => $data['Cost_Price'] ?? null,
            'Marked_Price'    => $data['Marked_Price'] ?? null,
            'Qty'             => $data['Qty'] ?? null,
            'Amount'          => $data['Amount'] ?? null,
            'Tr_Type'         => $data['Tr_Type'] ?? null,
            'Receipt_No'      => $data['Receipt_No'] ?? null,
            'SalesMan'        => $data['SalesMan'] ?? null,
            'Discount'        => $data['Discount'] ?? null,
            'Dis'             => $data['Dis'] ?? null,
            'Unit'            => $data['Unit'] ?? null,
            'Customer'        => $data['Customer'] ?? null,
            'BillDate'        => $data['BillDate'] ?? null,
            'BillTime'        => $data['BillTime'] ?? null,
            'ExchangeReceipt' => $data['ExchangeReceipt'] ?? null,
            'UserName'        => $data['UserName'] ?? null,
            'TransactionDate' => $data['TransactionDate'] ?? null,
            'InsertDate'      => $data['InsertDate'] ?? null,
            'R_No'            => $data['R_No'],
            'PC_ID'           => $data['PC_ID'] ?? null,
            'Merchant_ID'     => $data['merchant_ID'] ?? null,
            'Merchant_Name'   => $data['merchant_Name'] ?? null,
        ];

        return PosTransactionApi::create($mapped);
    }

    public function getPosSalesSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Loca' => 'required|string',
            'DateFrom' => 'required|date_format:Y-m-d',
            'DateTo' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $loca = $request->input('Loca');
        $startDate = new \DateTime($request->input('DateFrom'));
        $endDate = new \DateTime($request->input('DateTo'));
        
        $rangeEnd = clone $endDate;
        $rangeEnd->modify('+1 day');
        
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($startDate, $interval, $rangeEnd);

        $allResults = [];
        $lastErrorCode = 0;

        foreach ($period as $date) {
            $formattedDate = $date->format('d/m/Y');
            
            try {
                DB::statement("SET @pErrorCode = 0");
                
                $results = DB::select("CALL sp_PosSalesSummaryReportProcess(@pErrorCode, ?, ?, ?)", [
                    $loca,
                    $formattedDate,
                    $formattedDate
                ]);

                $errorCodeResult = DB::select("SELECT @pErrorCode as error_code");
                $currentErrorCode = $errorCodeResult[0]->error_code ?? 0;

                if ($currentErrorCode != 0 && $currentErrorCode != 50020) {
                    $lastErrorCode = $currentErrorCode;
                }

                if (!empty($results)) {
                    foreach ($results as $row) {
                        $row->DateFrom = $formattedDate;
                        $row->DateTo = $formattedDate;
                        $allResults[] = $row;
                    }
                }
            } catch (Exception $e) {
                Log::warning("SP skip for date {$formattedDate}: " . $e->getMessage());
                continue;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $allResults,
            'error_code' => $lastErrorCode
        ], 200);
    }
    
    public function processDayEnd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Loca' => 'required|string',
            'Date' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $loca = $request->input('Loca');
        $reportDate = date('d/m/Y', strtotime($request->input('Date')));
        $userName = $request->user() ? $request->user()->name : 'System';

        try {
            DB::statement("SET @pErr_x = 0");
            
            DB::select("CALL sp_DayEndProcess(@pErr_x, ?, ?, ?)", [
                $userName,
                $loca,
                $reportDate
            ]);

            $errorResult = DB::select("SELECT @pErr_x as error_code");
            $errorCode = $errorResult[0]->error_code ?? 0;

            if ($errorCode != 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Day end process error',
                    'error_code' => $errorCode
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Day end process completed successfully',
                'error_code' => $errorCode
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process day end',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
