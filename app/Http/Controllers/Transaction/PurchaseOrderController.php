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
use App\Http\Requests\Transaction\TempTransactionDetailRequest;
use App\Http\Requests\Transaction\TempTransactionHeaderRequest;
use App\Http\Resources\Transaction\TempTransactionDetailResource;
use App\Http\Resources\Transaction\TempTransactionHeaderResource;

class PurchaseOrderController extends Controller
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

    public function getTempPoNumber($loca_code)
    {
        try {
            $docCode = DocNumber::generate('TempPO', 'PO', 8, $loca_code);

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
            $existingProduct = TempTransactionDetail::where('doc_no', $data['doc_no'])
                ->where('prod_code', $data['prod_code'])
                ->first();

            if ($existingProduct) {
                $existingProduct->update([
                    'temp_transaction_header_id' => 0,
                    'purchase_price' => $data['purchase_price'],
                    'selling_price' => $data['selling_price'],
                    'updated_by' => auth()->id(),
                ]);
                $existingProduct->increment('pack_qty', $data['pack_qty']);
                $existingProduct->increment('qty', $data['qty']);
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
                    'qty' => $data['qty'],
                    'purchase_price' => $data['purchase_price'],
                    'selling_price' => $data['selling_price'],
                    'pack_size' => $data['pack_size'],
                    'pack_qty' => $data['pack_qty'],
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
            $productToUpdate = TempTransactionDetail::find($id);

            if (!$productToUpdate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            $productToUpdate->update($data);

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

    public function draftPurchaseOrder(TempTransactionHeaderRequest $request)
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

            $doc = DocNumber::where('type', 'TempPO')->first();
            if ($doc) {
                $doc->increment('last_id');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PO drafted successfully!',
                'data'  => new TempTransactionHeaderResource($tempHeader)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to draft the purchase order',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function updateDraftPurchaseOrder(TempTransactionHeaderRequest $request, $doc_no)
    {
        DB::beginTransaction();

        try {
            $purchaseOrder = TempTransactionHeader::where('doc_no', $doc_no)->first();

            if (!$purchaseOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase order not found.'
                ], 404);
            }

            $data = $request->validated();
            $data['updated_by'] = auth()->user()->id;
            $data = $this->processDiscountAndTax($data);

            $purchaseOrder->update($data);

            TempTransactionDetail::where('doc_no', $doc_no)->update([
                'temp_transaction_header_id' => $purchaseOrder->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase order updated successfully.',
                'data' => new TempTransactionHeaderResource($purchaseOrder->fresh())
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update purchase order.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function loadAllPurchaseOrders(Request $request)
    {
        if ($request->status == 'drafted') {
            $purchaseOrders = TempTransactionHeader::where('iid', 'PO')
                ->with('supplier')
                ->orderBy('id', 'desc')
                ->paginate(10);

            $formattedData = $purchaseOrders->getCollection()->map(function ($po) {
                $data = $po->toArray();
                $data['supplier_name'] = $po->supplier ? $po->supplier->sup_name : null;
                return $data;
            });

            $purchaseOrders->setCollection($formattedData);

            return response()->json([
                'success' => true,
                'message' => 'Draft purchase orders loaded successfully!',
                'status' => 'drafted',
                'data' => $purchaseOrders->items()
            ]);
        } else {
            $purchaseOrders = TransactionHeader::where('iid', 'PO')
                ->orderBy('id', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Applied purchase orders loaded successfully!',
                'status' => 'applied',
                'data' => $purchaseOrders->items()
            ]);
        }
    }

    public function loadPurchaseOrderByCode($doc_number, $status, $iid)
    {
        if ($status == 'applied') {
            $transactionHeaders = TransactionHeader::with('transactionDetails')->where(['doc_no' => $doc_number, 'iid' => "$iid"])->first();
            return response()->json([
                'success' => true,
                'message' => 'Purchase order loaded successfully!',
                'status' => 'applied',
                'data' => $transactionHeaders
            ]);
        } elseif ($status == 'drafted') {
            $tempTransactionHeaders = TempTransactionHeader::with(['TempTransactionDetails.product.unit', 'TempTransactionDetails' => function ($query) {
                $query->orderBy('line_no');
            }])->where(['doc_no' => $doc_number, 'iid' => "$iid"])->first();
            return response()->json([
                'success' => true,
                'message' => 'Purchase order loaded successfully!',
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
