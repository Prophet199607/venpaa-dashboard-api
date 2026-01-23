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
     * Insert POS Sales transactions
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
            $rNos = collect($details)->pluck('r_No')->unique()->toArray();
            $existingRNos = PosTransactionApi::whereIn('R_No', $rNos)
                ->pluck('R_No')
                ->toArray();

            DB::beginTransaction();

            $records = [];
            foreach ($details as $item) {
                if (in_array($item['r_No'], $existingRNos)) {
                    continue;
                }
                if (collect($records)->pluck('R_No')->contains($item['r_No'])) {
                    continue;
                }

                $records[] = $this->storeRecord($item);
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
