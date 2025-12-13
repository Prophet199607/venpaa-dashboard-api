<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Product;
use App\Models\Location;
use App\Models\DocNumber;
use App\Models\StockMaster;
use Illuminate\Http\Request;
use App\Models\TransactionDetail;
use App\Models\TransactionHeader;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\TempTransactionDetail;
use App\Models\TempTransactionHeader;
use App\Http\Requests\Transaction\TempTransactionHeaderRequest;

class StockAdjustmentController extends Controller
{
    private function getSessionDetails($docNo)
    {
        // Extract location from doc_no
        $prefixLength = 2;
        $locaCodeLength = 3;
        $locaCode = substr($docNo, $prefixLength, $locaCodeLength);

        // Get location details
        $location = null;
        if ($locaCode) {
            $location = Location::where('loca_code', $locaCode)->first();
        }

        $firstProduct = TempTransactionDetail::where('doc_no', $docNo)
            ->where('temp_transaction_header_id', 0)
            ->first();

        return [
            'doc_no' => $docNo,
            'location' => $location ? [
                'loca_code' => $location->loca_code,
                'loca_name' => $location->loca_name,
            ] : null,
            'product_count' => TempTransactionDetail::where('doc_no', $docNo)
                ->where('temp_transaction_header_id', 0)
                ->count(),
            'created_at' => $firstProduct ? $firstProduct->created_at : null,
        ];
    }

    public function getUnsavedSessions()
    {
        try {
            $unsavedSessions = TempTransactionDetail::where('created_by', auth()->id())
                ->where('iid', 'STA')
                ->where('temp_transaction_header_id', 0)
                ->distinct()
                ->pluck('doc_no');

            if ($unsavedSessions->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No unsaved sessions found.'
                ]);
            }

            // Get session details including location
            $sessionDetails = [];
            foreach ($unsavedSessions as $doc_no) {
                $sessionDetails[] = $this->getSessionDetails($doc_no);
            }

            return response()->json([
                'success' => true,
                'data' => $sessionDetails
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unsaved sessions.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getProductStock(Request $request)
    {
        try {
            $prodCode = $request->query('prod_code');
            $locaCode = $request->query('loca_code');

            if (!$prodCode || !$locaCode) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Product code and location code are required.'
                ], 400);
            }

            $query = StockMaster::where('prod_code', $prodCode)
                ->where('location', $locaCode);

            $totalQty = $query->sum('qty');
            $firstRecord = $query->first();

            if (!$firstRecord && $totalQty == 0) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'qty' => 0,
                        'purchase_price' => 0,
                        'selling_price' => 0
                    ]
                ]);
            }

            // Use prices from the first record, but qty is the sum
            return response()->json([
                'success' => true,
                'data' => [
                    'qty' => $totalQty,
                    'purchase_price' => $firstRecord ? $firstRecord->purchase_price : 0,
                    'selling_price' => $firstRecord ? $firstRecord->selling_price : 0,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product stock.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
