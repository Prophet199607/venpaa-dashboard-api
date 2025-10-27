<?php

namespace App\Http\Controllers\Transaction;

use Illuminate\Support\Facades\DB;
use App\Models\DocNumber;
use App\Models\PurchaseOrder;
use App\Http\Controllers\Controller;
use App\Models\TempTransactionDetail;
use App\Models\TempTransactionHeader;
use App\Http\Requests\Transaction\TempTransactionDetailRequest;
use App\Http\Resources\Transaction\TempTransactionDetailResource;

class PurchaseOrderController extends Controller
{
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

    public function getTempProducts($doc_no)
    {
        try {
            $products = TempTransactionDetail::where('doc_no', $doc_no)->orderBy('line_no')->get();
            return response()->json([
                'success' => true,
                'data' => TempTransactionDetailResource::collection($products),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch products.', 'error' => $e->getMessage()], 500);
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

    public function draftPurchaseOrder(TempTransactionDetailRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();
            $user = auth()->user();

            // Create temp header
            $tempHeader = TempTransactionHeader::create([
                'created_by'        => $user->id,
                'doc_no'            => $data['doc_no'],
                'iid'               => $data['iid'],
                'ref_number'        => $data['ref_number'],
                'document_date'     => $data['date'],
                'expected_date'     => $data['expected_date'],
                'transaction_date'  => $data['transaction_date'],
                'delivery_location' => $data['deliveryLocation'],
                'location'          => $data['selectedLocation'],
                'payment_mode'      => $data['payment_method'],
                'supplier_code'     => $data['selectedSupplier'],
                'remarks_ref'       => $data['remarks_ref'],
                'delivery_address'  => $data['delivery_address'],
                'invoice_amount'    => $data['invoice_amount'],
                'invoice_date'      => $data['invoice_date'],
                'invoice_no'        => $data['invoice_number'],
                'net_total'         => $data['netAmount'],
                'discount'          => $data['discount'],
                'dis_per'           => $data['dis_per'],
                'tax_per'           => $data['tax_per'],
                'tax'               => $data['tax'],
                'subtotal'          => $data['subtotal'],
                'grn_remarks'       => $data['grn_remarks'],
                'industry_code'     => $user->industry_code ?? null,
            ]);

            // Attach details to header
            TempTransactionDetail::where('doc_no', $data['doc_no'])
                ->where('industry_code', $user->industry_code)
                ->update([
                    'temp_transaction_header_id' => $tempHeader->id,
                ]);

            // Always handle as PO
            $docNoField = 'grn_no'; // For now, re-using field as in old code
            $docNumber = $data['po_no'] ?? null;

            // Update header with PO number if available
            if (!empty($docNumber)) {
                TempTransactionHeader::where([
                    'doc_no' => $data['doc_no'],
                    'industry_code' => $user->industry_code,
                ])->update([
                    $docNoField => $docNumber,
                ]);
            }

            // Increment DocNumber table for PO type only
            $doc = DocNumber::where('type', 'TempPO')->first();
            if ($doc) {
                $doc->increment('last_id');
            }

            // Fetch and return the updated header details
            $response_detail = TempTransactionHeader::where('doc_no', $data['doc_no'])->get();

            DB::commit();

            return response()->json([
                'type'    => 'success',
                'message' => 'PO drafted successfully!',
                'detail'  => $response_detail
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to draft the purchase order',
                'error'   => $e->getMessage(),
            ], 500);
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
}
