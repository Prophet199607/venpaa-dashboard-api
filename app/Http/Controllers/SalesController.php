<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PosTransactionApi;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SalesController extends Controller
{
    /**
     * Insert POS Sales transaction
     */
    public function InsertPosSales(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'R_No' => 'required|string|max:1000|unique:Pos_TransactionAPI,R_No',
            'Loca' => 'nullable|string|max:50',
            'Iid' => 'nullable|string|max:50',
            'Item_Code' => 'nullable|string|max:50',
            'Item_Descrip' => 'nullable|string|max:100',
            'Unit_Price' => 'nullable|numeric',
            'Cost_Price' => 'nullable|numeric',
            'Marked_Price' => 'nullable|numeric',
            'Qty' => 'nullable|numeric',
            'Amount' => 'nullable|numeric',
            'Tr_Type' => 'nullable|string|max:50',
            'Receipt_No' => 'nullable|string|max:50',
            'SalesMan' => 'nullable|string|max:50',
            'Discount' => 'nullable|numeric',
            'Dis' => 'nullable|string|max:50',
            'SBTT_Disc' => 'nullable|numeric',
            'Unit' => 'nullable|string|max:50',
            'Customer' => 'nullable|string|max:50',
            'BillDate' => 'nullable|string|max:50',
            'BillTime' => 'nullable|string|max:50',
            'ExchangeReceipt' => 'nullable|string|max:50',
            'UserName' => 'nullable|string|max:50',
            'TransactionDate' => 'nullable',
            'InsertDate' => 'nullable',
            'ProdId' => 'nullable|numeric',
            'UPD' => 'nullable|string|max:50',
            'ShiftEnd' => 'nullable|string|max:50',
            'DiscApp' => 'nullable|string|max:50',
            'Upload_Id' => 'nullable|string|max:50',
            'CrdNoteUPD' => 'nullable|string|max:50',
            'GiftIss_Id' => 'nullable|string|max:50',
            'GiftRece_Id' => 'nullable|string|max:50',
            'Adv_Upload' => 'nullable|string|max:50',
            'StaffUpload' => 'nullable|string|max:50',
            'SH_Qty' => 'nullable|string|max:50',
            'ExAllow' => 'nullable|string|max:50',
            'DiscAllow' => 'nullable|string|max:50',
            'ForeignProduct' => 'nullable|string|max:50',
            'Cost_Code' => 'nullable|string|max:50',
            'BatchCode' => 'nullable|string|max:50',
            'ShangrilaUpload' => 'nullable|integer',
            'Adv_Customer' => 'nullable|string|max:100',
            'Adv_Contact' => 'nullable|string|max:100',
            'Adv_NIC' => 'nullable|string|max:100',
            'Adv_Address' => 'nullable|string|max:250',
            'PC_ID' => 'nullable|string|max:300',
            'Merchant_ID' => 'nullable|string|max:200',
            'Merchant_Name' => 'nullable|string|max:250',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->all();          
            $transaction = PosTransactionApi::create($data);

            return response()->json([
                'success' => true,
                'message' => 'POS transaction inserted successfully',
                'data' => $transaction
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to insert POS transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
