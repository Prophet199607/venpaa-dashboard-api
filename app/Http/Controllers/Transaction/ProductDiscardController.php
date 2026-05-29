<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Location;
use App\Models\DocNumber;
use App\Models\DiscardType;
use App\Models\StockMaster;
use Illuminate\Http\Request;
use App\Models\TransactionDetail;
use App\Models\TransactionHeader;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\TempTransactionDetail;
use App\Models\TempTransactionHeader;
use App\Http\Requests\Transaction\TempTransactionDetailRequest;
use App\Http\Requests\Transaction\TempTransactionHeaderRequest;
use App\Http\Resources\Transaction\TempTransactionDetailResource;


class ProductDiscardController extends Controller
{
    public function loadAllTransactions(Request $request)
    {
        try {
            $iid = $request->input('iid', 'PD');
            $status = $request->input('status', 'drafted');
            $location = $request->input('location');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $perPage = $request->input('per_page', 10);

            $query = ($status === 'drafted') 
                ? TempTransactionHeader::query() 
                : TransactionHeader::query();

            $query->where('iid', $iid)->with('location');

            if ($location) {
                $query->where('location', $location);
            }

            if ($startDate && $endDate) {
                $query->whereBetween('document_date', [$startDate, $endDate]);
            }

            $transactions = $query->orderBy('id', 'desc')->paginate($perPage);

            // Format data to include location name
            $formattedData = collect($transactions->items())->map(function ($pd) {
                $pdArray = $pd->toArray();
                $locationRelation = $pd->getRelation('location');
                $pdArray['location_name'] = $locationRelation ? $locationRelation->loca_name : null;
                return $pdArray;
            });

            return response()->json([
                'success' => true,
                'data' => $formattedData,
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'total' => $transactions->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function loadTransactionByCode($doc_number, $status, $iid)
    {
        try {
            if ($status == 'applied') {
                $transaction = TransactionHeader::with([
                    'location',
                    'transactionDetails.product.unit',
                    'transactionDetails' => function ($query) {
                        $query->orderBy('line_no');
                    }
                ])
                ->where(['doc_no' => $doc_number, 'iid' => $iid])
                ->first();
            } else {
                $transaction = TempTransactionHeader::with([
                    'location',
                    'tempTransactionDetails.product.unit',
                    'tempTransactionDetails' => function ($query) {
                        $query->orderBy('line_no');
                    }
                ])
                ->where(['doc_no' => $doc_number, 'iid' => $iid])
                ->first();
            }

            return response()->json([
                'success' => true,
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
                ->where('iid', 'PD')
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

    public function getDiscardTypes()
    {
        try {
            $types = DiscardType::all();
            return response()->json([
                'success' => true,
                'data' => $types
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch discard types: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addProduct(TempTransactionDetailRequest $request)
    {
        try {
            $data = $request->validated();
            
            $packQty = $data['pack_qty'] ?? 0;
            $unitQty = $data['unit_qty'] ?? 0;
            $packSize = $data['pack_size'] ?? 0;
            $purchasePrice = $data['purchase_price'] ?? 0;

            $existingProduct = TempTransactionDetail::where('doc_no', $data['doc_no'])
                ->where('prod_code', $data['prod_code'])
                ->first();

            if ($existingProduct) {
                $newPackQty = $existingProduct->pack_qty + $packQty;
                $newUnitQty = $existingProduct->unit_qty + $unitQty;
                $totalQty = ($newPackQty * $packSize) + $newUnitQty;
                $amount = $totalQty * $purchasePrice;

                $existingProduct->update([
                    'temp_transaction_header_id' => 0,
                    'purchase_price' => $purchasePrice,
                    'selling_price' => $data['selling_price'] ?? 0,
                    'pack_size' => $packSize,
                    'pack_qty' => $newPackQty,
                    'unit_qty' => $newUnitQty,
                    'total_qty' => $totalQty,
                    'amount' => $amount,
                    'created_by' => auth()->id(),
                ]);
            } else {
                $totalQty = ($packQty * $packSize) + $unitQty;
                $amount = $totalQty * $purchasePrice;
                
                $maxLineNo = TempTransactionDetail::where('doc_no', $data['doc_no'])->max('line_no');
                $nextLineNo = $maxLineNo ? $maxLineNo + 1 : 1;
                TempTransactionDetail::create([
                    'temp_transaction_header_id' => 0,
                    'doc_no' => $data['doc_no'],
                    'prod_code' => $data['prod_code'],
                    'line_no' => $nextLineNo,
                    'iid' => $data['iid'],
                    'prod_name' => $data['prod_name'],
                    'purchase_price' => $purchasePrice,
                    'selling_price' => $data['selling_price'] ?? 0,
                    'pack_size' => $packSize,
                    'pack_qty' => $packQty,
                    'unit_qty' => $unitQty,
                    'total_qty' => $totalQty,
                    'amount' => $amount,
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

            $packQty = $data['pack_qty'] ?? 0;
            $unitQty = $data['unit_qty'] ?? 0;
            $packSize = $data['pack_size'] ?? 0;
            $purchasePrice = $data['purchase_price'] ?? 0;
            
            $totalQty = ($packQty * $packSize) + $unitQty;
            $amount = $totalQty * $purchasePrice;

            $productToUpdate->update([
                'purchase_price' => $purchasePrice,
                'selling_price' => $data['selling_price'] ?? 0,
                'pack_size' => $packSize,
                'pack_qty' => $packQty,
                'unit_qty' => $unitQty,
                'total_qty' => $totalQty,
                'amount' => $amount,
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
                $pdNumber = DocNumber::generate('PD', 'PD', 8, $data['location']);

                $headerData = $data;
                unset($headerData['id']);
                $transactionHeader = TransactionHeader::create(array_merge($headerData, [
                    'doc_no'      => $pdNumber,
                    'temp_doc_no' => $data['doc_no'],
                    'created_by'  => auth()->id(),
                ]));

                // Load temp products for this temp doc
                $tempProducts = TempTransactionDetail::where('doc_no', $data['doc_no'])
                    ->orderBy('line_no')
                    ->get();

                $transactionDetails = [];
                foreach ($tempProducts as $temp) {
                    $tempData = $temp->toArray();
                    unset($tempData['temp_transaction_header_id'], $tempData['id']);
                    $transactionDetail = TransactionDetail::create(array_merge($tempData, [
                        'transaction_header_id' => $transactionHeader->id,
                        'doc_no'                => $pdNumber,
                    ]));
                    $transactionDetails[] = $transactionDetail;
                }

                // Create StockMaster records for each product
                foreach ($transactionDetails as $detail) {
                    $totalQty = ($detail->pack_qty * $detail->pack_size) + $detail->unit_qty;
                    StockMaster::create([
                        'location' => $data['location'],
                        'transaction_date' => $data['document_date'],
                        'doc_no' => $pdNumber,
                        'prod_code' => $detail->prod_code,
                        'iid' => $data['iid'] ?? 'PD',
                        'qty' => -abs($totalQty),
                        'purchase_price' => $detail->purchase_price ?? 0.00,
                        'selling_price' => $detail->selling_price ?? 0.00,
                        'amount' => -abs($totalQty * ($detail->purchase_price ?? 0.00)),
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
                    'message' => 'Product discard stored successfully.',
                    'data'    => $transactionHeader->fresh(),
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to store product discard.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
