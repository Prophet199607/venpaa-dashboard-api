<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Product;
use App\Models\Location;
use App\Models\DocNumber;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\TempTransactionSaleDetail;
use App\Models\TempTransactionSaleHeader;
use App\Http\Requests\Transaction\TempTransactionSaleDetailRequest;
use App\Http\Requests\Transaction\TempTransactionSaleHeaderRequest;
use App\Http\Resources\Transaction\TempTransactionSaleDetailResource;

class InvoiceController extends Controller
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
        $firstProduct = TempTransactionSaleDetail::where('doc_no', $docNo)
            ->where('temp_transaction_sale_header_id', 0)
            ->first();

        $supplier = null;
        if ($firstProduct) {
            $product = Product::with('suppliers')->where('prod_code', $firstProduct->prod_code)->first();
            $supplier = $product && $product->suppliers ? $product->suppliers->first() : null;
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
            'product_count' => TempTransactionSaleDetail::where('doc_no', $docNo)
                ->where('temp_transaction_sale_header_id', 0)
                ->count(),
            'created_at' => $firstProduct ? $firstProduct->created_at : null,
        ];
    }

    public function getUnsavedSessions()
    {
        try {
            $unsavedSessions = TempTransactionSaleDetail::where('created_by', auth()->id())
                ->where('iid', 'INV')
                ->where('temp_transaction_sale_header_id', 0)
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

    public function getTempProducts($doc_no)
    {
        try {
            $products = TempTransactionSaleDetail::where('doc_no', $doc_no)
                ->where('temp_transaction_sale_header_id', 0)
                ->with('product.unit')
                ->get();

            // Get session details including location and supplier
            $sessionDetails = $this->getSessionDetails($doc_no);

            return response()->json([
                'success' => true,
                'data' => TempTransactionSaleDetailResource::collection($products),
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

    public function addProduct(TempTransactionSaleDetailRequest $request)
    {
        $data = $request->validated();
        try {
            $data = $request->validated();
            $existingProduct = TempTransactionSaleDetail::where('doc_no', $data['doc_no'])
                ->where('prod_code', $data['prod_code'])
                ->first();

            if ($existingProduct) {
                $existingProduct->update([
                    'temp_transaction_sale_header_id' => 0,
                    'purchase_price' => $data['purchase_price'],
                    'selling_price' => $data['selling_price'],
                    'created_by' => auth()->id(),
                ]);
                $existingProduct->increment('pack_qty', $data['pack_qty']);
                $existingProduct->increment('unit_qty', $data['unit_qty']);
                $existingProduct->increment('free_qty', $data['free_qty'] ?? 0);
                $existingProduct->increment('total_qty', $data['total_qty']);
                $existingProduct->increment('amount', $data['amount']);
            } else {
                $maxLineNo = TempTransactionSaleDetail::where('doc_no', $data['doc_no'])->max('line_no');
                $nextLineNo = $maxLineNo ? $maxLineNo + 1 : 1;
                TempTransactionSaleDetail::create([
                    'temp_transaction_sale_header_id' => 0,
                    'doc_no' => $data['doc_no'],
                    'line_no' => $nextLineNo,
                    'iid' => $data['iid'],
                    'type' => $data['type'],
                    'prod_code' => $data['prod_code'],
                    'prod_name' => $data['prod_name'],
                    'purchase_price' => $data['purchase_price'],
                    'selling_price' => $data['selling_price'],
                    'pack_size' => $data['pack_size'],
                    'pack_qty' => $data['pack_qty'],
                    'unit_qty' => $data['unit_qty'],
                    'free_qty' => $data['free_qty'] ?? 0,
                    'total_qty' => $data['total_qty'],
                    'amount' => $data['amount'],
                    'line_wise_discount_value' => $data['line_wise_discount_value'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }

            $response_detail = TempTransactionSaleDetail::where('doc_no',  $data['doc_no'])->orderBy('line_no')->get();

            return response()->json([
                'success' => true,
                'message' => 'Product added successfully!',
                'data' => TempTransactionSaleDetailResource::collection($response_detail),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateProduct(TempTransactionSaleDetailRequest $request, $id)
    {
        try {
            $data = $request->validated();
            $productToUpdate = TempTransactionSaleDetail::find($id);

            if (!$productToUpdate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            $productToUpdate->update([
                'purchase_price' => $data['purchase_price'] ?? 0,
                'selling_price' => $data['selling_price'] ?? 0,
                'pack_size' => $data['pack_size'] ?? 1,
                'pack_qty' => $data['pack_qty'] ?? 0,
                'unit_qty' => $data['unit_qty'] ?? 0,
                'free_qty' => $data['free_qty'] ?? 0,
                'total_qty' => $data['total_qty'] ?? 0,
                'line_wise_discount_value' => $data['line_wise_discount_value'],
                'amount' => $data['amount'] ?? 0,
                'updated_by' => auth()->id(),
            ]);

            $response_details = TempTransactionSaleDetail::with('product.unit')
                ->where('doc_no', $productToUpdate->doc_no)
                ->orderBy('line_no')
                ->get();


            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully!',
                'data' => TempTransactionSaleDetailResource::collection($response_details),

            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
