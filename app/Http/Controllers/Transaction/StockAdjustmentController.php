<?php

namespace App\Http\Controllers\Transaction;

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
        $prefixLength   = 3;
        $locaCodeLength = 3;
        $locaCode = substr($docNo, $prefixLength, $locaCodeLength);

        $location = null;
        if ($locaCode) {
            $location = Location::where('loca_code', $locaCode)->first();
        }

        $firstProduct = TempTransactionDetail::where('doc_no', $docNo)
            ->where('temp_transaction_header_id', 0)
            ->first();

        return [
            'doc_no'        => $docNo,
            'location'      => $location ? [
                'loca_code' => $location->loca_code,
                'loca_name' => $location->loca_name,
            ] : null,
            'product_count' => TempTransactionDetail::where('doc_no', $docNo)
                ->where('temp_transaction_header_id', 0)
                ->count(),
            'created_at'    => $firstProduct ? $firstProduct->created_at : null,
        ];
    }
    
    private function calcAmount(float $physicalTotalQty, float $totalQty, float $purchasePrice): float
    {
        $variance = $physicalTotalQty - $totalQty;
        return round($variance * $purchasePrice, 6);
    }

    private function recalcTempHeaderTotals(string $docNo): array
    {
        $subtotal = (float) TempTransactionDetail::where('doc_no', $docNo)->sum('amount');
        return [
            'subtotal'  => round($subtotal, 6),
            'net_total' => round($subtotal, 6),
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
                    'data'    => [],
                    'message' => 'No unsaved sessions found.',
                ]);
            }

            $sessionDetails = [];
            foreach ($unsavedSessions as $doc_no) {
                $sessionDetails[] = $this->getSessionDetails($doc_no);
            }

            return response()->json([
                'success' => true,
                'data'    => $sessionDetails,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unsaved sessions.',
                'error'   => $e->getMessage(),
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
                    'message' => 'Product code and location code are required.',
                ], 400);
            }

            $stats = StockMaster::where('prod_code', $prodCode)
                ->where('location', $locaCode)
                ->where('iid', '!=', 'CREATE')
                ->selectRaw('SUM(qty) as total_qty, MAX(id) as max_id')
                ->first();

            $totalQty = $stats ? (float) $stats->total_qty : 0;
            $firstRecord = ($stats && $stats->max_id) ? StockMaster::find($stats->max_id) : null;

            if (!$firstRecord && $totalQty == 0) {
                return response()->json([
                    'success' => true,
                    'data'    => [
                        'qty'            => 0,
                        'purchase_price' => 0,
                        'selling_price'  => 0,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'qty'            => $totalQty,
                    'purchase_price' => $firstRecord ? $firstRecord->purchase_price : 0,
                    'selling_price'  => $firstRecord ? $firstRecord->selling_price  : 0,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product stock.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function addProduct(TempTransactionDetailRequest $request)
    {
        try {
            $data = $request->validated();

            $physicalTotalQty = (float) ($data['physical_total_qty'] ?? 0);
            $totalQty         = (float) ($data['total_qty']          ?? 0);
            $purchasePrice    = (float) ($data['purchase_price']     ?? 0);
            $amount           = $this->calcAmount($physicalTotalQty, $totalQty, $purchasePrice);

            $existingProduct = TempTransactionDetail::where('doc_no', $data['doc_no'])
                ->where('prod_code', $data['prod_code'])
                ->first();

            if ($existingProduct) {
                $newPhysicalTotal = $physicalTotalQty;
                $newTotal         = $totalQty;
                $newAmount        = $this->calcAmount($newPhysicalTotal, $newTotal, $purchasePrice);

                $existingProduct->update([
                    'temp_transaction_header_id' => 0,
                    'purchase_price'             => $purchasePrice,
                    'selling_price'              => $data['selling_price'] ?? $existingProduct->selling_price,
                    'pack_qty'                   => $data['pack_qty'],
                    'unit_qty'                   => $data['unit_qty'],
                    'total_qty'                  => $newTotal,
                    'physical_pack_qty'          => $data['physical_pack_qty'],
                    'physical_unit_qty'          => $data['physical_unit_qty'],
                    'physical_total_qty'         => $newPhysicalTotal,
                    'amount'                     => $newAmount,
                    'created_by'                 => auth()->id(),
                ]);
            } else {
                $maxLineNo  = TempTransactionDetail::where('doc_no', $data['doc_no'])->max('line_no');
                $nextLineNo = $maxLineNo ? $maxLineNo + 1 : 1;

                TempTransactionDetail::create([
                    'temp_transaction_header_id' => 0,
                    'doc_no'                     => $data['doc_no'],
                    'prod_code'                  => $data['prod_code'],
                    'line_no'                    => $nextLineNo,
                    'iid'                        => $data['iid'],
                    'prod_name'                  => $data['prod_name'],
                    'purchase_price'             => $purchasePrice,
                    'selling_price'              => $data['selling_price'] ?? 0,
                    'pack_size'                  => $data['pack_size'],
                    'pack_qty'                   => $data['pack_qty'],
                    'unit_qty'                   => $data['unit_qty'],
                    'total_qty'                  => $totalQty,
                    'physical_pack_qty'          => $data['physical_pack_qty'],
                    'physical_unit_qty'          => $data['physical_unit_qty'],
                    'physical_total_qty'         => $physicalTotalQty,
                    'amount'                     => $amount,
                    'created_by'                 => auth()->id(),
                ]);
            }

            $this->syncTempHeaderTotals($data['doc_no']);

            $response_detail = TempTransactionDetail::where('doc_no', $data['doc_no'])->orderBy('line_no')->get();

            return response()->json([
                'success' => true,
                'message' => 'Product added successfully!',
                'data'    => TempTransactionDetailResource::collection($response_detail),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add product',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function updateProduct(TempTransactionDetailRequest $request, $id)
    {
        try {
            $data            = $request->validated();
            $productToUpdate = TempTransactionDetail::find($id);

            if (!$productToUpdate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            $physicalTotalQty = (float) ($data['physical_total_qty'] ?? 0);
            $totalQty         = (float) ($data['total_qty']          ?? 0);
            $purchasePrice    = (float) ($data['purchase_price']     ?? 0);
            $amount           = $this->calcAmount($physicalTotalQty, $totalQty, $purchasePrice);

            $productToUpdate->update([
                'purchase_price'     => $purchasePrice,
                'selling_price'      => $data['selling_price'] ?? $productToUpdate->selling_price,
                'pack_size'          => $data['pack_size'],
                'pack_qty'           => $data['pack_qty'],
                'unit_qty'           => $data['unit_qty'],
                'total_qty'          => $totalQty,
                'physical_pack_qty'  => $data['physical_pack_qty'],
                'physical_unit_qty'  => $data['physical_unit_qty'],
                'physical_total_qty' => $physicalTotalQty,
                'amount'             => $amount,
                'updated_by'         => auth()->user()->id,
            ]);

            $this->syncTempHeaderTotals($productToUpdate->doc_no);

            $response_detail = TempTransactionDetail::where('doc_no', $productToUpdate->doc_no)->orderBy('line_no')->get();

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully!',
                'data'    => TempTransactionDetailResource::collection($response_detail),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function draft(TempTransactionHeaderRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $data['created_by'] = auth()->user()->id;
            $data['iid']        = 'STA';

            $totals = $this->recalcTempHeaderTotals($data['doc_no']);
            $data['subtotal']  = $totals['subtotal'];
            $data['net_total'] = $totals['net_total'];

            $tempHeader = TempTransactionHeader::create($data);

            TempTransactionDetail::where('doc_no', $data['doc_no'])->update([
                'temp_transaction_header_id' => $tempHeader->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjustment drafted successfully!',
                'data'    => $tempHeader->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to draft stock adjustment.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function updateDraft(TempTransactionHeaderRequest $request, $doc_no)
    {
        DB::beginTransaction();
        try {
            $tempHeader = TempTransactionHeader::where('doc_no', $doc_no)->first();

            if (!$tempHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Draft not found.',
                ], 404);
            }

            $data = $request->validated();
            $data['updated_by'] = auth()->user()->id;

            $totals = $this->recalcTempHeaderTotals($doc_no);
            $data['subtotal']  = $totals['subtotal'];
            $data['net_total'] = $totals['net_total'];

            $tempHeader->update($data);

            TempTransactionDetail::where('doc_no', $doc_no)->update([
                'temp_transaction_header_id' => $tempHeader->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjustment draft updated successfully.',
                'data'    => $tempHeader->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update draft.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function store(TempTransactionHeaderRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $data      = $request->validated();
                $staNumber = DocNumber::generate('STA', 'STA', 8, $data['location']);

                $totals = $this->recalcTempHeaderTotals($data['doc_no']);

                $headerData = $data;
                unset($headerData['id']);

                $transactionHeader = TransactionHeader::create(array_merge($headerData, [
                    'doc_no'      => $staNumber,
                    'temp_doc_no' => $data['doc_no'],
                    'subtotal'    => $totals['subtotal'],
                    'net_total'   => $totals['net_total'],
                    'created_by'  => auth()->id(),
                ]));

                $tempProducts = TempTransactionDetail::where('doc_no', $data['doc_no'])
                    ->orderBy('line_no')
                    ->get();

                $transactionDetails = [];
                foreach ($tempProducts as $temp) {
                    $tempData = $temp->toArray();
                    unset($tempData['temp_transaction_header_id'], $tempData['id']);

                    $transactionDetail = TransactionDetail::create(array_merge($tempData, [
                        'transaction_header_id' => $transactionHeader->id,
                        'doc_no'                => $staNumber,
                    ]));
                    $transactionDetails[] = $transactionDetail;
                }

                foreach ($transactionDetails as $detail) {
                    $physicalTotal = (float) ($detail->physical_total_qty ?? 0);
                    $currentTotal  = (float) ($detail->total_qty          ?? 0);
                    $varianceQty   = $physicalTotal - $currentTotal;
                    $purchasePrice = (float) ($detail->purchase_price     ?? 0);
                    $amount        = round($varianceQty * $purchasePrice, 6);

                    StockMaster::create([
                        'location'         => $data['location'],
                        'transaction_date' => $data['document_date'],
                        'doc_no'           => $staNumber,
                        'prod_code'        => $detail->prod_code,
                        'iid'              => $data['iid'] ?? 'STA',
                        'qty'              => $varianceQty,
                        'purchase_price'   => $purchasePrice,
                        'selling_price'    => (float) ($detail->selling_price ?? 0),
                        'amount'           => $amount,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }

                // Clean up temp data
                TempTransactionDetail::where('doc_no', $data['doc_no'])->delete();
                TempTransactionHeader::where('doc_no', $data['doc_no'])->delete();

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

    private function syncTempHeaderTotals(string $docNo): void
    {
        $header = TempTransactionHeader::where('doc_no', $docNo)->first();
        if ($header) {
            $totals = $this->recalcTempHeaderTotals($docNo);
            $header->update([
                'subtotal'  => $totals['subtotal'],
                'net_total' => $totals['net_total'],
            ]);
        }
    }
}
