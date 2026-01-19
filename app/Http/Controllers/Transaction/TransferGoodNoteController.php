<?php

namespace App\Http\Controllers\Transaction;

use Carbon\Carbon;
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
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\Transaction\TempTransactionDetailRequest;
use App\Http\Requests\Transaction\TempTransactionHeaderRequest;
use App\Http\Resources\Transaction\TempTransactionDetailResource;
use App\Http\Resources\Transaction\TempTransactionHeaderResource;


class TransferGoodNoteController extends Controller
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

        // Get supplier from the first product in the session
        $firstProduct = TempTransactionDetail::where('doc_no', $docNo)
            ->where('temp_transaction_header_id', 0)
            ->first();

        $supplier = null;
        if ($firstProduct) {
            $product = Product::with('suppliers')->where('prod_code', $firstProduct->prod_code)->first();
            $supplier = ($product && $product->suppliers) ? $product->suppliers->first() : null;
        }

        return [
            'doc_no' => $docNo,
            'location' => $location ? [
                'loca_code' => $location->loca_code,
                'loca_name' => $location->loca_name,
            ] : null,
            'supplier' => $supplier ? [
                'sup_code' => $supplier->sup_code,
                'sup_name' => $supplier->sup_name,
            ] : null,
            'product_count' => TempTransactionDetail::where('doc_no', $docNo)
                ->where('temp_transaction_header_id', 0)
                ->count(),
            'created_at' => $firstProduct ? $firstProduct->created_at : null,
        ];
    }

    public function getAppliedTransactions(Request $request)
    {
        try {
            $iid = $request->input('iid');
            $location = $request->input('location');
            $exclude_iid = $request->input('exclude_iid');

            if (!$iid || !$location) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required parameters: iid and location are required',
                    'data' => []
                ], 400);
            }

            $appliedTransaction = TransactionHeader::where([
                'iid' => $exclude_iid,
                'location' => $location,
            ])->pluck('recall_doc_no');

            $recallTransactions = TransactionHeader::where([
                'iid' => $iid,
                'location' => $location,
            ])->where(function ($query) use ($appliedTransaction) {
                $query->whereNotIn('doc_no', $appliedTransaction);
            })->get();


            $formatted = $recallTransactions->map(function ($item) {
                return [
                    'doc_no' => $item->doc_no,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Applied transactions loaded successfully!',
                'data' => $formatted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch applied transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUnsavedSessions()
    {
        try {
            $unsavedSessions = TempTransactionDetail::where('created_by', auth()->id())
                ->where('iid', 'TGN')
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

            $filteredSessions = [];
            foreach ($unsavedSessions as $doc_no) {
                $cacheKey = 'transaction_tgn_session' . $doc_no;

                $transactionMetadata = Cache::get($cacheKey);

                if (!$transactionMetadata) {
                    $filteredSessions[] = $doc_no;
                } else {
                    $loadedTime = Carbon::parse($transactionMetadata['loaded_at']);
                    if ($loadedTime->diffInMinutes(now()) > 10) {
                        TempTransactionDetail::where('doc_no', $doc_no)
                            ->where('temp_transaction_header_id', 0)
                            ->delete();
                        TempTransactionHeader::where('doc_no', $doc_no)->delete();
                        Cache::forget($cacheKey);
                    }
                }
            }

            $sessionDetails = [];
            foreach ($filteredSessions as $doc_no) {
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
                $existingProduct->increment('amount', $data['amount']);
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
                    'amount' => $data['amount'],
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
                'amount' => $data['amount'],
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
                $tgnNumber = DocNumber::generate('TGN', 'TGN', 8, $data['location']);

                $headerData = $data;
                unset($headerData['id']);
                $transactionHeader = TransactionHeader::create([
                    ...$headerData,
                    'doc_no'      => $tgnNumber,
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
                        'doc_no'                => $tgnNumber,
                    ]);
                    $transactionDetails[] = $transactionDetail;
                }

                // Create StockMaster records for each product
                foreach ($transactionDetails as $detail) {
                    StockMaster::create([
                        'location' => $data['location'],
                        'transaction_date' => $data['document_date'],
                        'doc_no' => $tgnNumber,
                        'prod_code' => $detail->prod_code,
                        'iid' => $data['iid'] ?? 'TGN',
                        'qty' => -(abs($detail->total_qty ?? 0.000)),
                        'purchase_price' => $detail->purchase_price ?? 0.00,
                        'selling_price' => $detail->selling_price ?? 0.00,
                        'amount' => -(abs($detail->amount ?? 0.00)),
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
                    'message' => 'Transfer good note stored successfully.',
                    'data'    => $transactionHeader->fresh(),
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to store transfer good note.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
