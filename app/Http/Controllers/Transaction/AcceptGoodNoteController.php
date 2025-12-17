<?php

namespace App\Http\Controllers\Transaction;

use App\Models\DocNumber;
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
use App\Http\Resources\Transaction\TempTransactionHeaderResource;
use App\Http\Resources\Transaction\TempTransactionDetailResource;

class AcceptGoodNoteController extends Controller
{
    public function loadAllAgns(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $userLocation = $user->location;

        if ($request->status == 'pending') {
            $usedDocNos = TransactionHeader::where('iid', 'AGN')
                ->whereNotNull('recall_doc_no')
                ->pluck('recall_doc_no')
                ->toArray();

            $pendingAgn = TransactionHeader::where('iid', $request->iid)
                ->where('delivery_location', $userLocation)
                ->whereNotIn('doc_no', $usedDocNos)
                ->orderBy('id', 'desc')
                ->paginate(10);

            $formattedData = $pendingAgn->getCollection()->map(function ($agn) {
                $data = $agn->toArray();
                return $data;
            });

            $pendingAgn->setCollection($formattedData);

            return response()->json([
                'success' => true,
                'message' => 'Pending AGN loaded successfully!',
                'status' => 'pending',
                'user_location' => $userLocation,
                'data' => $pendingAgn->items()
            ]);
        } else {
            $appliedAgn = TransactionHeader::where('iid', $request->iid)
                ->where('delivery_location', $userLocation)
                ->orderBy('id', 'desc')
                ->paginate(10);

            $formattedData = $appliedAgn->getCollection()->map(function ($agn) {
                $data = $agn->toArray();
                return $data;
            });

            $appliedAgn->setCollection($formattedData);

            return response()->json([
                'success' => true,
                'message' => 'Applied AGN loaded successfully!',
                'status' => 'applied',
                'user_location' => $userLocation,
                'data' => $appliedAgn->items()
            ]);
        }
    }

    public function loadAgnByCode($doc_number, $status, $iid)
    {
        if ($status == 'applied') {
            $transactionHeaders = TransactionHeader::with([
                'location',
                'deliveryLocation',
                'transactionDetails.product.unit',
                'transactionDetails' => function ($query) {
                    $query->orderBy('line_no');
                }
            ])
            ->where(['doc_no' => $doc_number, 'iid' => "$iid"])
            ->first();

            return response()->json([
                'success' => true,
                'message' => 'Applied AGN loaded successfully!',
                'status' => 'applied',
                'data' => $transactionHeaders
            ]);
        } elseif ($status == 'pending') {
            $transactionHeaders = TransactionHeader::with([
                'location',
                'deliveryLocation',
                'transactionDetails.product.unit',
                'transactionDetails' => function ($query) {
                    $query->orderBy('line_no');
                }
            ])
            ->where(['doc_no' => $doc_number, 'iid' => "$iid"])
            ->first();

            return response()->json([
                'success' => true,
                'message' => 'Pending AGN loaded successfully!',
                'status' => 'pending',
                'data' => $transactionHeaders
            ]);
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

    public function draftAgn(TempTransactionHeaderRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();
            $existingTempHeaders = TempTransactionHeader::where('recall_doc_no', $data['recall_doc_no'])
                ->where('iid', 'AGN')
                ->where('created_by', auth()->id())
                ->get();

            foreach ($existingTempHeaders as $header) {
                TempTransactionDetail::where('temp_transaction_header_id', $header->id)->delete();
                $header->delete();
            }

            $data['created_by'] = auth()->user()->id;
            $tempHeader = TempTransactionHeader::create($data);

            $sourceProducts = TransactionDetail::where('doc_no', $data['recall_doc_no'])
                ->orderBy('line_no')
                ->get();

            foreach ($sourceProducts as $sourceProduct) {
                $detailData = $sourceProduct->toArray();
                unset($detailData['id'], $detailData['transaction_header_id'], $detailData['created_at'], $detailData['updated_at']);

                $detailData['temp_transaction_header_id'] = $tempHeader->id;
                $detailData['doc_no'] = $data['doc_no'];
                $detailData['iid'] = $data['iid'];
                $detailData['created_by'] = auth()->id();

                TempTransactionDetail::create($detailData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Drafted AGN successfully!',
                'data'  => new TempTransactionHeaderResource($tempHeader)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to draft the transaction',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function store(TempTransactionHeaderRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->validated();
                $agnNumber = DocNumber::generate('AGN', 'AGN', 8, $data['delivery_location']);

                $headerData = $data;
                unset($headerData['id']);
                $transactionHeader = TransactionHeader::create([
                    ...$headerData,
                    'doc_no'      => $agnNumber,
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
                        'doc_no'                => $agnNumber,
                    ]);
                    $transactionDetails[] = $transactionDetail;
                }

                // Create StockMaster records for each product
                foreach ($transactionDetails as $detail) {
                    StockMaster::create([
                        'location' => $data['delivery_location'],
                        'transaction_date' => $data['document_date'],
                        'doc_no' => $agnNumber,
                        'prod_code' => $detail->prod_code,
                        'iid' => $data['iid'] ?? 'AGN',
                        'qty' => $detail->total_qty ?? 0.000,
                        'purchase_price' => $detail->purchase_price ?? 0.00,
                        'selling_price' => $detail->selling_price ?? 0.00,
                        'amount' => $detail->amount ?? 0.00,
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
                    'message' => 'Accept good note stored successfully.',
                    'data'    => $transactionHeader->fresh(),
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to store accept good note.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
