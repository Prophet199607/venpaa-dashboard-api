<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Product;
use App\Models\Location;
use App\Models\DocNumber;
use Illuminate\Http\Request;
use App\Models\TransactionDetail;
use App\Models\TransactionHeader;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\TempTransactionDetail;
use App\Models\TempTransactionHeader;
use App\Models\ItemTransactionDetail;
use App\Models\ItemTransactionHeader;
use App\Http\Requests\Transaction\TempTransactionDetailRequest;
use App\Http\Requests\Transaction\TempTransactionHeaderRequest;
use App\Http\Resources\Transaction\TempTransactionDetailResource;
use App\Http\Resources\Transaction\TempTransactionHeaderResource;

class ItemRequestController extends Controller
{
    private function processDiscountAndTax(array $data): array
    {
        // Handle discount
        if (isset($data['discount']) && $data['discount'] > 0) {
            $data['dis_per'] = 0;
        } elseif (isset($data['dis_per']) && $data['dis_per'] > 0) {
            $data['discount'] = 0;
        } else {
            $data['discount'] = 0;
            $data['dis_per'] = 0;
        }

        // Handle tax
        if (isset($data['tax']) && $data['tax'] > 0) {
            $data['tax_per'] = 0;
        } elseif (isset($data['tax_per']) && $data['tax_per'] > 0) {
            $data['tax'] = 0;
        } else {
            $data['tax'] = 0;
            $data['tax_per'] = 0;
        }

        return $data;
    }

    private function processLineWiseDiscount(array $data): array
    {
        if (isset($data['line_wise_discount_value'])) {
            $discountStr = $data['line_wise_discount_value'];
            if (is_string($discountStr) && str_ends_with($discountStr, '%')) {
                $percentage = (float) rtrim($discountStr, '%');
                $packQty = (float) ($data['pack_qty'] ?? 0);
                $packSize = (float) ($data['pack_size'] ?? 0);
                $uniQty = (float) ($data['unit_qty'] ?? 0);
                $totalQty = ($packQty * $packSize) + $uniQty;
                $amountBeforeDiscount = $data['purchase_price'] * $totalQty;
                $data['line_wise_discount_value'] = ($amountBeforeDiscount * $percentage) / 100;
            } else {
                $data['line_wise_discount_value'] = (float) $discountStr;
            }
        } else {
            $data['line_wise_discount_value'] = 0;
        }
        return $data;
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

        // Get supplier from the first product in the session
        $firstProduct = TempTransactionDetail::where('doc_no', $docNo)
            ->where('temp_transaction_header_id', 0)
            ->first();

        $supplier = null;
        if ($firstProduct) {
            $product = Product::with('supplierDetails')->where('prod_code', $firstProduct->prod_code)->first();
            $supplier = $product?->supplierDetails;
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

    public function getTempIrNumber($loca_code)
    {
        try {
            $docCode = DocNumber::generate('TempIR', 'IR', 8, $loca_code);

            return response()->json([
                'success' => true,
                'message' => 'Code generated successfully',
                'code' => $docCode
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getTempProducts($doc_no)
    {
        try {
            $products = TempTransactionDetail::where('doc_no', $doc_no)
                ->where('temp_transaction_header_id', 0)
                ->with('product.unit')
                ->get();

            // Get session details including location and supplier
            $sessionDetails = $this->getSessionDetails($doc_no);

            return response()->json([
                'success' => true,
                'data' => TempTransactionDetailResource::collection($products),
                'session_details' => $sessionDetails
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch temp products.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addProduct(TempTransactionDetailRequest $request)
    {
        try {
            $data = $request->validated();
            $data = $this->processLineWiseDiscount($data);
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
                $existingProduct->increment('free_qty', $data['free_qty']);
                $existingProduct->increment('total_qty', $data['total_qty']);
                $existingProduct->increment('amount', $data['amount']);
                $existingProduct->increment('line_wise_discount_value', $data['line_wise_discount_value']);
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
                    'free_qty' => $data['free_qty'],
                    'total_qty' => $data['total_qty'],
                    'amount' => $data['amount'],
                    'line_wise_discount_value' => $data['line_wise_discount_value'],
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
            $data = $this->processLineWiseDiscount($data);
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
                'free_qty' => $data['free_qty'],
                'total_qty' => $data['total_qty'],
                'line_wise_discount_value' => $data['line_wise_discount_value'],
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

    public function deleteTempDetail($doc_no, $line_no)
    {
        try {
            TempTransactionDetail::where(['doc_no' => $doc_no, 'line_no' => $line_no])->delete();
            $rowsToUpdate = TempTransactionDetail::where('doc_no', $doc_no)
                ->where('line_no', '>', $line_no)
                ->orderBy('line_no')
                ->get();

            foreach ($rowsToUpdate as $row) {
                $row->line_no = $row->line_no - 1;
                $row->save();
            }

            $response_detail = TempTransactionDetail::where('doc_no', $doc_no)->orderBy('line_no')->get();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully!',
                'data' => TempTransactionDetailResource::collection($response_detail),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function draftItemRequest(TempTransactionHeaderRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();
            $data['created_by'] = auth()->user()->id;
            $data = $this->processDiscountAndTax($data);

            $tempHeader = TempTransactionHeader::create($data);

            TempTransactionDetail::where('doc_no', $data['doc_no'])
                ->update([
                    'temp_transaction_header_id' => $tempHeader->id,
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'IR drafted successfully!',
                'data'  => new TempTransactionHeaderResource($tempHeader)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to draft the item request',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function updateDraftItemRequest(TempTransactionHeaderRequest $request, $doc_no)
    {
        DB::beginTransaction();

        try {
            $itemRequest = TempTransactionHeader::where('doc_no', $doc_no)->first();

            if (!$itemRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item request not found.'
                ], 404);
            }

            $data = $request->validated();
            $data['updated_by'] = auth()->user()->id;
            $data = $this->processDiscountAndTax($data);

            $itemRequest->update($data);

            TempTransactionDetail::where('doc_no', $doc_no)->update([
                'temp_transaction_header_id' => $itemRequest->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item request updated successfully.',
                'data' => new TempTransactionHeaderResource($itemRequest->fresh())
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update item request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(TempTransactionHeaderRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $this->processDiscountAndTax($request->validated());
                $irNumber = DocNumber::generate('IR', 'IR', 8, $data['location']);

                $headerData = $data;
                unset($headerData['id']);
                $transactionHeader = ItemTransactionHeader::create([
                    ...$headerData,
                    'doc_no'      => $irNumber,
                    'temp_doc_no' => $data['doc_no'],
                    'created_by'  => auth()->id(),
                ]);

                // Load temp products for this temp doc
                $tempProducts = TempTransactionDetail::where('doc_no', $data['doc_no'])
                    ->orderBy('line_no')
                    ->get();

                foreach ($tempProducts as $temp) {
                    $tempData = $temp->toArray();
                    unset($tempData['temp_transaction_header_id'], $tempData['id']);
                    ItemTransactionDetail::create([
                        ...$tempData,
                        'item_transaction_header_id'    => $transactionHeader->id,
                        'doc_no'       => $irNumber,
                        'created_by'   => auth()->id(),
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
                    'message' => 'Item request stored successfully.',
                    'data'    => $transactionHeader->fresh(),
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to store item request.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function loadAllItemRequests(Request $request)
    {
        if ($request->status == 'drafted') {
            $itemRequests = TempTransactionHeader::where('iid', $request->iid)
                ->with('supplier')
                ->orderBy('id', 'desc')
                ->paginate(10);

            $formattedData = $itemRequests->getCollection()->map(function ($ir) {
                $data = $ir->toArray();
                $data['supplier_name'] = $ir->supplier ? $ir->supplier->sup_name : null;
                return $data;
            });

            $itemRequests->setCollection($formattedData);

            return response()->json([
                'success' => true,
                'message' => 'Draft item requests loaded successfully!',
                'status' => 'drafted',
                'data' => $itemRequests->items()
            ]);
        } else {
            $itemRequests = ItemTransactionHeader::where('iid', $request->iid)
                ->with('supplier')
                ->orderBy('id', 'desc')
                ->paginate(10);

            $formattedData = $itemRequests->getCollection()->map(function ($po) {
                $data = $po->toArray();
                $data['supplier_name'] = $po->supplier ? $po->supplier->sup_name : null;
                return $data;
            });

            $itemRequests->setCollection($formattedData);

            return response()->json([
                'success' => true,
                'message' => 'Applied item requests loaded successfully!',
                'status' => 'applied',
                'data' => $itemRequests->items()
            ]);
        }
    }

    public function loadItemRequestByCode($doc_number, $status, $iid)
    {
        if ($status == 'applied') {
            $itemTransactionHeaders = ItemTransactionHeader::with([
                'supplier',
                'location',
                'deliveryLocation',
                'itemTransactionDetails' => function ($query) {
                    $query->orderBy('line_no');
                },
                'itemTransactionDetails.product.unit'
            ])
            ->where(['doc_no' => $doc_number, 'iid' => $iid])
            ->first();

            return response()->json([
                'success' => true,
                'message' => 'Item request loaded successfully!',
                'status' => 'applied',
                'data' => $itemTransactionHeaders
            ]);
        } elseif ($status == 'drafted') {
            $tempTransactionHeaders = TempTransactionHeader::with([
                'supplier',
                'location',
                'deliveryLocation',
                'tempTransactionDetails' => function ($query) {
                    $query->orderBy('line_no');
                },
                'tempTransactionDetails.product.unit'
            ])
            ->where(['doc_no' => $doc_number, 'iid' => $iid])
            ->first();

            return response()->json([
                'success' => true,
                'message' => 'Item request loaded successfully!',
                'status' => 'drafted',
                'data' => $tempTransactionHeaders
            ]);
        }
    }

    public function removeUnsaved($doc_no)
    {
        try {
            TempTransactionDetail::where([
                'doc_no' => $doc_no,
                'temp_transaction_header_id' => 0
            ])->delete();

            return response()->json([
                'success' => true,
                'message' => 'Temporary products cleared successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear products.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getUnsavedSessions()
    {
        try {
            $unsavedSessions = TempTransactionDetail::where('created_by', auth()->id())
                ->where('iid', 'IR')
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

            // Get session details including location and supplier
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
}
