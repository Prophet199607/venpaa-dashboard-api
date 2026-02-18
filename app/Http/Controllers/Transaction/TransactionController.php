<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Product;
use App\Models\Location;
use App\Models\DocNumber;
use Illuminate\Http\Request;
use App\Models\PaymentSummary;
use App\Models\TransactionHeader;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\TempTransactionDetail;
use App\Models\TempTransactionHeader;
use App\Http\Requests\Transaction\TempTransactionDetailRequest;
use App\Http\Requests\Transaction\TempTransactionHeaderRequest;
use App\Http\Resources\Transaction\TempTransactionDetailResource;
use App\Http\Resources\Transaction\TempTransactionHeaderResource;

class TransactionController extends Controller
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

    public function getTempTransactionNumber($type, $loca_code)
    {
        try {
            $doc = DocNumber::where('type', $type)->first();

            if (!$doc) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid document type: $type"
                ], 404);
            }

            $generatedCode = DocNumber::generate(
                $doc->type,
                $doc->prefix,
                $doc->length,
                $loca_code
            );

            return response()->json([
                'success' => true,
                'message' => 'Code generated successfully',
                'code' => $generatedCode,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate code',
                'error' => $e->getMessage(),
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

    public function getAppliedTransactions(Request $request)
    {
        try {

            $iid = $request->input('iid');
            $location = $request->input('location');
            $supplier = $request->input('supplier');
            $recall_iid = $request->input('recall_iid');

            $appliedTransaction = TransactionHeader::where([
                'iid' => $iid,
                'location' => $location,
                'supplier_code' => $supplier,
            ])->pluck('recall_doc_no');

            $recallTransactions = TransactionHeader::where([
                'iid' => $recall_iid,
                'location' => $location,
                'supplier_code' => $supplier,
            ])->where(function ($query) use ($appliedTransaction) {
                $query->whereNotIn('doc_no', $appliedTransaction);
            })->get();

            $formatted = $recallTransactions->map(function ($item) {
                    return [
                        'doc_no' => $item->doc_no,
                        'sup_name' => isset($item->supplier) ? $item->supplier->sup_name : 'N/A',
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

    public function loadAllTransactions(Request $request)
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

        if ($request->status == 'drafted') {
            $tempTransactionData = TempTransactionHeader::where('iid', $request->iid)
                ->where('location', $userLocation)
                ->whereDate('document_date', '>=', $startDate)
                ->whereDate('document_date', '<=', $endDate)
                ->with('supplier')
                ->orderBy('id', 'desc')
                ->paginate(10);

            $formattedData = collect($tempTransactionData->items())->map(function ($transaction) {
                $data = $transaction->toArray();
                $data['supplier_name'] = $transaction->supplier ? $transaction->supplier->sup_name : null;
                return $data;
            });

            return response()->json([
                'success' => true,
                'message' => 'Draft transactions loaded successfully!',
                'status' => 'drafted',
                'data' => $formattedData,
            ]);
        } else {
            $transactionData = TransactionHeader::where('iid', $request->iid)
                ->where('location', $userLocation)
                ->whereDate('document_date', '>=', $startDate)
                ->whereDate('document_date', '<=', $endDate)
                ->with('supplier')
                ->orderBy('id', 'desc')
                ->paginate(10);

            $formattedData = collect($transactionData->items())->map(function ($transaction) {
                $data = $transaction->toArray();
                $data['supplier_name'] = $transaction->supplier ? $transaction->supplier->sup_name : null;
                return $data;
            });

            return response()->json([
                'success' => true,
                'message' => 'Applied transactions loaded successfully!',
                'status' => 'applied',
                'data' => $formattedData,
            ]);
        }
    }

    public function loadTransactionByCode($doc_number, $status, $iid)
    {
        if ($status == 'applied') {
            $transactionHeaders = TransactionHeader::with([
                'supplier',
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
                'message' => 'Transaction loaded successfully!',
                'status' => 'applied',
                'data' => $transactionHeaders
            ]);
        } elseif ($status == 'drafted') {
            $tempTransactionHeaders = TempTransactionHeader::with([
                'supplier',
                'location',
                'deliveryLocation',
                'tempTransactionDetails.product.unit',
                'tempTransactionDetails' => function ($query) {
                    $query->orderBy('line_no');
                }
            ])
            ->where(['doc_no' => $doc_number, 'iid' => "$iid"])
            ->first();

            return response()->json([
                'success' => true,
                'message' => 'Transaction loaded successfully!',
                'status' => 'drafted',
                'data' => $tempTransactionHeaders
            ]);
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

    public function removeUnsaved($doc_no)
    {
        try {
            TempTransactionDetail::where([
                'doc_no' => $doc_no,
                'temp_transaction_header_id' => 0
            ])->delete();

            TempTransactionHeader::where('doc_no', $doc_no)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Temporary data cleared successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function draftTransaction(TempTransactionHeaderRequest $request)
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
                'message' => 'Drafted successfully!',
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

    public function updateTransaction(TempTransactionHeaderRequest $request, $doc_no)
    {
        DB::beginTransaction();

        try {
            $transactionDetail = TempTransactionHeader::where('doc_no', $doc_no)->first();

            if (!$transactionDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found.'
                ], 404);
            }

            $data = $request->validated();
            $data['updated_by'] = auth()->user()->id;
            $data = $this->processDiscountAndTax($data);

            $transactionDetail->update($data);

            TempTransactionDetail::where('doc_no', $doc_no)->update([
                'temp_transaction_header_id' => $transactionDetail->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction updated successfully.',
                'data' => new TempTransactionHeaderResource($transactionDetail->fresh())
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update transaction.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function AdvanceStore(Request $request)
    {
        DB::beginTransaction();
        try {
            $advance = $request->input('advance');

            if (!empty($advance['customer'])) {
                $acc_code =  $advance['customer'];
                $acc_type = 'customer';
                $iid = 'CADV';
            } elseif (!empty($advance['supplier'])) {
                $acc_code = $advance['supplier'];
                $acc_type = 'supplier';
                $iid = 'SADV';
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer or Supplier selection is required.'
                ], 400);
            }

            $location_code = $advance['location'] ?? '';

            $docRecord = DocNumber::where('type', $iid)->first();
            if ($docRecord && $docRecord->length !== 8) {
                $docRecord->update(['length' => 8]);
            }

            $doc_number = DocNumber::generate($iid, $iid, 8, $location_code, false);

            // Save PaymentSummary
            PaymentSummary::create([
                'acc_code'          => $acc_code,
                'acc_type'          => $acc_type,
                'iid'               => $iid,
                'doc_no'            => $doc_number,
                'transaction_amount' => abs($advance['amount']),
                'transaction_date'  => $advance['paymentDate'] ?? now(),
                'document_date'     => $advance['documentDate'] ?? now(),
                'location'          => $location_code,
                'month_end'         => 0,
                'balance_amount'    => abs($advance['amount']),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Advance stored successfully',
                'doc_no'  => $doc_number
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to store advance payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
