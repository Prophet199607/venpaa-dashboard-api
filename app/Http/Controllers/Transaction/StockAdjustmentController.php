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
use App\Http\Requests\Transaction\TempTransactionDetailRequest;
use App\Http\Resources\Transaction\TempTransactionDetailResource;

class StockAdjustmentController extends Controller
{
    private function getSessionDetails($docNo)
    {
        // Extract location from doc_no
        $prefixLength = 3;
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

    public function addProduct(TempTransactionDetailRequest $request)
    {
        try {
            $data = $request->validated();
            $existingProduct = TempTransactionDetail::where('doc_no', $data['doc_no'])
                ->where('prod_code', $data['prod_code'])
                ->first();

            if ($existingProduct) {
                $existingProduct->update([
                    'temp_transaction_header_id' => 0,
                    'purchase_price' => $data['purchase_price'],
                    'selling_price' => $data['selling_price'],
                    'created_by' => auth()->id(),
                ]);
                $existingProduct->increment('pack_qty', $data['pack_qty']);
                $existingProduct->increment('unit_qty', $data['unit_qty']);
                $existingProduct->increment('total_qty', $data['total_qty']);
                $existingProduct->increment('physical_total_qty', $data['physical_total_qty']);
            } else {
                $maxLineNo = TempTransactionDetail::where('doc_no', $data['doc_no'])->max('line_no');
                $nextLineNo = $maxLineNo ? $maxLineNo + 1 : 1;
                TempTransactionDetail::create([
                    'temp_transaction_header_id' => 0,
                    'doc_no' => $data['doc_no'],
                    'prod_code' => $data['prod_code'],
                    'line_no' => $nextLineNo,
                    'iid' => $data['iid'],
                    'prod_name' => $data['prod_name'],
                    'purchase_price' => $data['purchase_price'],
                    'selling_price' => $data['selling_price'],
                    'pack_size' => $data['pack_size'],
                    'pack_qty' => $data['pack_qty'],
                    'unit_qty' => $data['unit_qty'],
                    'total_qty' => $data['total_qty'],
                    'physical_pack_qty' => $data['physical_pack_qty'],
                    'physical_unit_qty' => $data['physical_unit_qty'],
                    'physical_total_qty' => $data['physical_total_qty'],
                    'created_by' => auth()->id(),
                ]);
            }

            $response_detail = TempTransactionDetail::where('doc_no',  $data['doc_no'])->orderBy('line_no')->get();

            return response()->json([
                'success' => true,
                'message' => 'Product added successfully!',
                'data' => TempTransactionDetailResource::collection($response_detail),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateProduct(TempTransactionDetailRequest $request, $id)
    {
        try {
            $data = $request->validated();
            $productToUpdate = TempTransactionDetail::find($id);

            if (!$productToUpdate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            $productToUpdate->update([
                'purchase_price' => $data['purchase_price'],
                'selling_price' => $data['selling_price'],
                'pack_size' => $data['pack_size'],
                'pack_qty' => $data['pack_qty'],
                'unit_qty' => $data['unit_qty'],
                'total_qty' => $data['total_qty'],
                'physical_pack_qty' => $data['physical_pack_qty'],
                'physical_unit_qty' => $data['physical_unit_qty'],
                'physical_total_qty' => $data['physical_total_qty'],
                'updated_by' => auth()->user()->id,
           ]);

           $response_detail = TempTransactionDetail::where('doc_no',  $productToUpdate->doc_no)->orderBy('line_no')->get();

           return response()->json([
                'success' => true,
                'message' => 'Product updated successfully!',
                'data' => TempTransactionDetailResource::collection($response_detail),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(TempTransactionHeaderRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->validated();
                $staNumber = DocNumber::generate('STA', 'STA', 8, $data['location']);

                $headerData = $data;
                unset($headerData['id']);
                $transactionHeader = TransactionHeader::create([
                    ...$headerData,
                    'doc_no'      => $staNumber,
                    'temp_doc_no' => $data['doc_no'],
                    'created_by'  => auth()->id(),
                ]);

                // Load temp products for this temp doc
                $tempProducts = TempTransactionDetail::where('doc_no', $data['doc_no'])
                    ->orderBy('line_no')
                    ->get();

                $transactionDetails = [];
                foreach ($tempProducts as $temp) {
                    $tempData = $temp->toArray();
                    unset($tempData['temp_transaction_header_id'], $tempData['id']);
                    $transactionDetail = TransactionDetail::create([
                        ...$tempData,
                        'transaction_header_id' => $transactionHeader->id,
                        'doc_no'                => $staNumber,
                    ]);
                    $transactionDetails[] = $transactionDetail;
                }

                // Create StockMaster records for each product
                foreach ($transactionDetails as $detail) {
                    $qty = ($detail->physical_total_qty ?? 0) - ($detail->total_qty ?? 0);
                    StockMaster::create([
                        'location' => $data['location'],
                        'transaction_date' => $data['document_date'],
                        'doc_no' => $staNumber,
                        'prod_code' => $detail->prod_code,
                        'iid' => $data['iid'] ?? 'STA',
                        'qty' => $qty,
                        'purchase_price' => $detail->purchase_price ?? 0.00,
                        'selling_price' => $detail->selling_price ?? 0.00,
                        'amount' => '0.00',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Clean up temp details for this doc
                if (TempTransactionDetail::where('doc_no', $data['doc_no'])->exists()) {
                    TempTransactionDetail::where('doc_no', $data['doc_no'])->delete();
                }

                // Clean up temp header for this doc
                if (TempTransactionHeader::where('doc_no', $data['doc_no'])->exists()) {
                    TempTransactionHeader::where('doc_no', $data['doc_no'])->delete();
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Stock adjustment stored successfully.',
                    'data'    => $transactionHeader->fresh(),
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to store stock adjustment.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
