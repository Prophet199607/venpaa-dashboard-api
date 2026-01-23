<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PosTransactionApi;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;

class SalesController extends Controller
{
    /**
     * Insert POS Sales transactions in bulk.
     * 
     * Structure: { "details": [ { ... }, { ... } ] }
     */
    public function InsertPosSales(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'details' => 'required|array|min:1',
            'details.*.r_No' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $details = $request->input('details');

        try {
            // Check for duplicates within the request batch
            $rNos = collect($details)->pluck('r_No');
            if ($rNos->count() !== $rNos->unique()->count()) {
                throw new Exception('Duplicate r_No found within the request batch.');
            }

            // Check if any r_No already exists in the database
            $existing = PosTransactionApi::whereIn('R_No', $rNos)->first();
            if ($existing) {
                throw new Exception("The reference number '{$existing->R_No}' already exists in our records.");
            }

            DB::beginTransaction();

            $records = [];
            foreach ($details as $item) {
                $records[] = $this->storeRecord($item);
            }

            DB::commit();

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'POS transactions summary saved successfully',
                'data' => $records
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Map request keys to DB columns and store the record.
     */
    private function storeRecord(array $data)
    {
        $mapped = [
            'Loca'            => $data['loca'] ?? null,
            'Iid'             => $data['iid'] ?? null,
            'Item_Code'       => $data['item_Code'] ?? null,
            'Item_Descrip'    => $data['item_Descrip'] ?? null,
            'Unit_Price'      => $data['unit_Price'] ?? null,
            'Cost_Price'      => $data['cost_Price'] ?? null,
            'Marked_Price'    => $data['marked_Price'] ?? null,
            'Qty'             => $data['qty'] ?? null,
            'Amount'          => $data['amount'] ?? null,
            'Tr_Type'         => $data['tr_Type'] ?? null,
            'Receipt_No'      => $data['receipt_No'] ?? null,
            'SalesMan'        => $data['salesMan'] ?? null,
            'Discount'        => $data['discount'] ?? null,
            'Dis'             => $data['dis'] ?? null,
            'Unit'            => $data['unit'] ?? null,
            'Customer'        => $data['customer'] ?? null,
            'BillDate'        => $data['billDate'] ?? null,
            'BillTime'        => $data['billTime'] ?? null,
            'ExchangeReceipt' => $data['exchangeReceipt'] ?? null,
            'UserName'        => $data['userName'] ?? null,
            'TransactionDate' => $data['transactionDate'] ?? null,
            'InsertDate'      => $data['insertDate'] ?? null,
            'R_No'            => $data['r_No'],
            'PC_ID'           => $data['pC_ID'] ?? null,
            'BatchCode'       => $data['batch_Code'] ?? null,
            'Merchant_ID'     => $data['merchant_ID'] ?? null,
            'Merchant_Name'   => $data['merchant_Name'] ?? null,
        ];

        return PosTransactionApi::create($mapped);
    }
}
