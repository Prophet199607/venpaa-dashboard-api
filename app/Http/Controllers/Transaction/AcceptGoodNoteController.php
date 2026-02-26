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
        $startDate = $request->input('start_date') ?: now()->format('Y-m-d');
        $endDate = $request->input('end_date') ?: now()->format('Y-m-d');
        $perPage = $request->input('per_page', 10);

        if ($request->status == 'pending') {
            $usedDocNos = TransactionHeader::where('iid', 'AGN')
                ->whereNotNull('recall_doc_no')
                ->pluck('recall_doc_no')
                ->toArray();

            $pendingAgn = TransactionHeader::where('iid', $request->iid)
                ->where('delivery_location', $userLocation)
                ->whereDate('document_date', '>=', $startDate)
                ->whereDate('document_date', '<=', $endDate)
                ->whereNotIn('doc_no', $usedDocNos)
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            $formattedData = collect($pendingAgn->items())->map(function ($agn) {
                $data = $agn->toArray();
                return $data;
            });

            // $formattedData = $pendingAgn->getCollection()->map(function ($agn) {
            //     $data = $agn->toArray();
            //     return $data;
            // });

            // $pendingAgn->setCollection($formattedData);

            return response()->json([
                'success' => true,
                'message' => 'Pending AGN loaded successfully!',
                'status' => 'pending',
                'user_location' => $userLocation,
                'data' => $formattedData,
            ]);
        } else {
            $appliedAgn = TransactionHeader::where('iid', $request->iid)
                ->where('delivery_location', $userLocation)
                ->whereDate('document_date', '>=', $startDate)
                ->whereDate('document_date', '<=', $endDate)
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            $formattedData = collect($appliedAgn->items())->map(function ($agn) {
                $data = $agn->toArray();
                return $data;
            });

            // $formattedData = $appliedAgn->getCollection()->map(function ($agn) {
            //     $data = $agn->toArray();
            //     return $data;
            // });

            // $appliedAgn->setCollection($formattedData);

            return response()->json([
                'success' => true,
                'message' => 'Applied AGN loaded successfully!',
                'status' => 'applied',
                'user_location' => $userLocation,
                'data' => $formattedData,    
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

            $response_details = TempTransactionDetail::with('product.unit')
                ->where('doc_no', $productToUpdate->doc_no)
                ->orderBy('line_no')
                ->get();


            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully!',
                'data' => TempTransactionDetailResource::collection($response_details),

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

            $tempDetails = TempTransactionDetail::with('product.unit')->where('temp_transaction_header_id', $tempHeader->id)->orderBy('line_no')->get();
            $tempHeader->setRelation('transactionDetails', $tempDetails);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Drafted AGN successfully!',
                'data'  => new TempTransactionHeaderResource($tempHeader),
                'transaction_details' => TempTransactionDetailResource::collection($tempDetails),
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

                $response = [
                    'success' => true,
                    'message' => 'Accept good note stored successfully.',
                    'data'    => $transactionHeader->fresh(),
                ];

                if ($request->boolean('is_return')) {
                    $deliveryLocation = $data['location'];
                    $location = $data['delivery_location'];
                    $tgrNumber = DocNumber::generate('TempTGR', 'TGR', 8, $location);

                    $tempHeader = TempTransactionHeader::create([
                        'doc_no'            => $tgrNumber,
                        'document_date'     => $data['document_date'],
                        'location'          => $location, // TGN Location
                        'delivery_location' => $deliveryLocation, // AGN Location
                        'remarks_ref'       => $data['remarks_ref'],
                        'recall_doc_no'     => $agnNumber,
                        'iid'               => 'TGR',
                        'created_by'        => auth()->id(),
                        'subtotal'          => 0,
                        'net_total'         => 0,
                    ]);

                    $products = $request->input('products', []);
                    $totalAmount = 0;

                    foreach ($products as $product) {
                        $varPack = (float) ($product['variance_pack_qty'] ?? 0);
                        $varUnit = (float) ($product['variance_unit_qty'] ?? 0);

                        // Store only available product records (with variance)
                        if ($varPack == 0 && $varUnit == 0) continue;

                        $packSize = (float) ($product['pack_size'] ?? 1);
                        $totalQty = ($varPack * $packSize) + $varUnit;
                        $purchasePrice = (float) ($product['purchase_price'] ?? 0);
                        $amount = $totalQty * $purchasePrice;
                        $totalAmount += $amount;

                        TempTransactionDetail::create([
                            'temp_transaction_header_id' => $tempHeader->id,
                            'doc_no'                     => $tgrNumber,
                            'line_no'                    => $product['line_no'] ?? 0,
                            'prod_code'                  => $product['prod_code'],
                            'prod_name'                  => $product['prod_name'],
                            'pack_size'                  => $packSize,
                            'pack_qty'                   => $varPack,
                            'unit_qty'                   => $varUnit,
                            'total_qty'                  => $totalQty,
                            'purchase_price'             => $purchasePrice,
                            'selling_price'              => $product['selling_price'] ?? 0,
                            'amount'                     => $amount,
                            'iid'                        => 'TGR',
                            'created_by'                 => auth()->id(),
                        ]);
                    }

                    $tempHeader->update(['subtotal' => $totalAmount, 'net_total' => $totalAmount]);

                    $response = [
                        'success' => true,
                        'message' => 'Return Note drafted successfully.',
                        'data' => $tempHeader
                    ];
                }

                if (TempTransactionDetail::where('doc_no', $data['doc_no'])->exists()) {
                    TempTransactionDetail::where('doc_no', $data['doc_no'])->delete();
                }

                if (TempTransactionHeader::where('doc_no', $data['doc_no'])->exists()) {
                    TempTransactionHeader::where('doc_no', $data['doc_no'])->delete();
                }

                DB::commit();
                return response()->json($response);
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
