<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Product;
use App\Models\Location;
use App\Models\DocNumber;
use App\Models\StockMaster;
use App\Models\CompanyHeader;
use Illuminate\Http\Request;
use App\Models\PaymentSummary;
use App\Models\PaidPaymentDetail;
use App\Models\PaidPaymentSummary;
use App\Models\ProductSaleSummary;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\TransactionSaleDetail;
use App\Models\TransactionSaleHeader;
use App\Models\TempTransactionSaleDetail;
use App\Models\TempTransactionSaleHeader;
use App\Http\Requests\Transaction\TempTransactionSaleDetailRequest;
use App\Http\Requests\Transaction\TempTransactionSaleHeaderRequest;
use App\Http\Resources\Transaction\TempTransactionSaleDetailResource;
use App\Http\Resources\Transaction\TempTransactionSaleHeaderResource;

class InvoiceController extends Controller
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
                $amountBeforeDiscount = $data['selling_price'] * $totalQty;
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

    public function loadAllInvoices(Request $request)
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
            $tempTransactionSaleHeaders = TempTransactionSaleHeader::where('iid', $request->iid)
                ->where('location', $userLocation)
                ->whereDate('document_date', '>=', $startDate)
                ->whereDate('document_date', '<=', $endDate)
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Draft invoice loaded successfully!',
                'status' => 'pending',
                'data' => $tempTransactionSaleHeaders->items(),
            ]);
        } else {
            $transactionSaleHeaders = TransactionSaleHeader::where('iid', $request->iid)
                ->where('location', $userLocation)

                ->orderBy('id', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Applied invoice loaded successfully!',
                'status' => 'applied',
                'data' => $transactionSaleHeaders->items(),
            ]);
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

    public function deleteTempDetail($doc_no, $line_no)
    {
        try {
            TempTransactionSaleDetail::where(['doc_no' => $doc_no, 'line_no' => $line_no])->delete();
            $rowsToUpdate = TempTransactionSaleDetail::where('doc_no', $doc_no)
                ->where('line_no', '>', $line_no)
                ->orderBy('line_no')
                ->get();

            foreach ($rowsToUpdate as $row) {
                $row->line_no = $row->line_no - 1;
                $row->save();
            }

            $response_detail = TempTransactionSaleDetail::where('doc_no', $doc_no)->orderBy('line_no')->get();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully!',
                'data' => TempTransactionSaleDetailResource::collection($response_detail),
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
            TempTransactionSaleDetail::where([
                'doc_no' => $doc_no,
                'temp_transaction_sale_header_id' => 0
            ])->delete();

            TempTransactionSaleHeader::where('doc_no', $doc_no)->delete();

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

    public function draftInvoice(TempTransactionSaleHeaderRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();
            $data['created_by'] = auth()->user()->id;

            if (isset($data['is_vat']) && $data['is_vat'] == true) {
                $data['vat_percent'] = 18;
            }

            $data = $this->processDiscountAndTax($data);

            $tempHeader = TempTransactionSaleHeader::create($data);

            TempTransactionSaleDetail::where('doc_no', $data['doc_no'])
                ->update([
                    'temp_transaction_sale_header_id' => $tempHeader->id,
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Drafted successfully!',
                'data'  => new TempTransactionSaleHeaderResource($tempHeader)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to draft the invoice',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function updateInvoice(TempTransactionSaleHeaderRequest $request, $doc_no)
    {
        DB::beginTransaction();

        try {
            $transactionDetail = TempTransactionSaleHeader::where('doc_no', $doc_no)->first();

            if (!$transactionDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found.'
                ], 404);
            }

            $data = $request->validated();
            $data['updated_by'] = auth()->user()->id;

            if (isset($data['is_vat']) && $data['is_vat'] == true) {
                $data['vat_percent'] = 18;
            }

            $data = $this->processDiscountAndTax($data);

            $transactionDetail->update($data);

            TempTransactionSaleDetail::where('doc_no', $doc_no)->update([
                'temp_transaction_sale_header_id' => $transactionDetail->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Invoice updated successfully.',
                'data' => new TempTransactionSaleHeaderResource($transactionDetail->fresh())
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

    public function store(TempTransactionSaleHeaderRequest $request)
    {

        try {
            DB::beginTransaction();

            // ---------------------------------------------------------
            // 1. Prepare Data & Generate Invoice Number
            // ---------------------------------------------------------
            $data = $request->validated();
            $payments = $request->input('payments', []);
            $location_code = $data['location'] ?? '';

            $invNumber = DocNumber::generate('INV', 'INV', 8, $location_code);

            // Read refund flag from payload (supports both root and inv nodes)
            $refund = strtolower($request->input('refund', $request->inv['refund'] ?? ''));
            $isRefundYes = ($refund === 'yes');
            $type = strtolower($data['type'] ?? 'sales');

            $org_pmt_doc_no = null;

            // Generate CAR number for Return with Refund Yes
            if ($type === 'return' && $isRefundYes) {
                $org_pmt_doc_no = DocNumber::generate('CashRefund', 'CAR', 8, $location_code);
            }

            // ---------------------------------------------------------
            // 2. Create Transaction Sale Header
            // ---------------------------------------------------------
            $headerData = $data;
            unset($headerData['id']); // Remove ID if present from request/validated data

            if (isset($headerData['is_vat']) && $headerData['is_vat'] == true) {
                $headerData['vat_percent'] = 18;
            }

            if ($type === 'return') {
                $headerData['subtotal'] = abs($headerData['subtotal'] ?? 0);
                $headerData['net_total'] = abs($headerData['net_total'] ?? 0);
                $headerData['tax'] = abs($headerData['tax'] ?? 0);
                $headerData['discount'] = abs($headerData['discount'] ?? 0);
            }

            $transactionSaleHeader = TransactionSaleHeader::create(
                array_merge(
                    $headerData,
                    [
                        'doc_no'      => $invNumber,
                        'temp_doc_no' => $data['doc_no'] ?? '',
                        'created_by'  => auth()->id(),
                        'iid'         => 'INV',
                    ]
                )
            );

            $payments = $request->payments ?? [];

            foreach ($payments as $paymentDetails) {

                $method = strtoupper($paymentDetails['method'] ?? '');

                $isAdvanceMode = $method === 'ADVANCE' && isset($paymentDetails['advanceId']);
                $isOverPaymentMode = ($method === 'OVER PAYMENT' || $method === 'OVERPAYMENT') && (isset($paymentDetails['overPaymentId']) || isset($paymentDetails['overDocNo']));
                $isReturn = $method === 'RETURN' && isset($paymentDetails['returnId']);

                // Detect document number
                if ($isAdvanceMode) {
                    $advanceLikeDocNo = $paymentDetails['advanceId'];
                } elseif ($isOverPaymentMode) {
                    $advanceLikeDocNo = $paymentDetails['overPaymentId'];
                } elseif ($isReturn) {
                    $advanceLikeDocNo = $paymentDetails['returnId'];
                } else {
                    $advanceLikeDocNo = null;
                }

                // Get amount (frontend sends 'amount')
                $advanceLikeAmount = (float)($paymentDetails['amount'] ?? 0);

                // Save logic
                if ($advanceLikeDocNo) {

                    $advanceRecord = PaymentSummary::where([
                        'doc_no' => $advanceLikeDocNo,
                        'acc_code' => $request->customer_code,
                        'iid' => $paymentDetails['IID'],
                    ])->first();

                    if ($advanceRecord) {

                        $deductAmount = min($advanceRecord->balance_amount, $advanceLikeAmount);

                        $advanceRecord->balance_amount -= $deductAmount;

                        $advanceRecord->save();
                    }
                }
            }


            // ---------------------------------------------------------
            // 3. Handle Payments & Receipts
            // ---------------------------------------------------------

            $totalPaid = 0;
            if (!empty($payments)) {
                foreach ($payments as $payment) {
                    $totalPaid += (float)($payment['amount'] ?? 0);
                }
            }

            $netAmount = (float)($data['net_total'] ?? 0);

            // For negative net with refund yes, we effectively "paid" the customer
            if ($type === 'return' && $isRefundYes && $netAmount < 0 && empty($payments)) {
                $totalPaid = $netAmount;
            }

            $balanceAmount = $netAmount - $totalPaid;

            if ($type === 'return') {
                $netAmount = abs($netAmount);
                $totalPaid = abs($totalPaid);
                $balanceAmount = abs($balanceAmount);
            }

            // If it's a return "no", no need to save in PaidPaymentSummary or PaidPaymentDetail
            if ($type === 'return' && !$isRefundYes) {
                // Skip payment entries for credit returns
            } elseif ($org_pmt_doc_no || $totalPaid != 0) {
                if (!$org_pmt_doc_no) {
                    // Generate Receipt Number if not already set (e.g. not a CAR)
                    $org_pmt_doc_no = DocNumber::generate('Receipt', 'REC', 8, $location_code);
                }

                // If it's a return "yes", no need to save in PaidPaymentSummaries table
                if ($type === 'return' && $isRefundYes) {
                    // Skip saving in PaidPaymentSummaries table per user request
                } else {
                    foreach ($payments as $payment) {
                        $amount = abs((float)($payment['amount'] ?? 0));
                        if ($amount == 0) continue;

                        PaidPaymentSummary::create([
                            'location'      => $location_code,
                            'temp_doc_no'   => $data['doc_no'] ?? '',
                            'org_doc_no'    => $org_pmt_doc_no,
                            'doc_no'        => $invNumber,
                            'payment_mode'  => $payment['method'] ?? $payment['mode'] ?? 'CASH',
                            'amount'        => $amount,
                            'iid'           => 'REC',
                            'acc_code'      => $data['customer_code'],
                            'transaction_date' => $payment['date'] ?? now(),
                            'document_date' => $data['document_date'] ?? now(),
                            'bank_name'     => $payment['bank'] ?? null,
                            'branch'        => $payment['branch'] ?? null,
                            'cheque_no'     => $payment['chequeNo'] ?? null,
                        ]);
                    }
                }

                // Create Paid Payment Link (Invoice <-> Payment)
                PaidPaymentDetail::create([
                    'org_doc_no' => $org_pmt_doc_no,
                    'doc_no'     => $invNumber,
                    'location'   => $location_code,
                    'transaction_amount' => $netAmount,
                    'transaction_date'   => $data['document_date'] ?? now(),
                    'balance_amount'     => $balanceAmount,
                    'paid_amount'        => $totalPaid,
                    'temp_doc_no'        => $data['doc_no'] ?? '',
                    'iid'                => ($type === 'return' && $isRefundYes) ? 'CAR' : 'REC',
                    'acc_code'           => $data['customer_code'],
                    'document_date'      => $data['document_date'] ?? now(),
                    'setoff_sr_doc'      => 0,
                ]);
            }

            // 3.4 Update Customer Ledger (Payment Summary)
            // If there's a transaction amount, record it against the customer
            if ($netAmount != 0) {
                PaymentSummary::create([
                    'acc_code'      => $data['customer_code'],
                    'location'      => $location_code,
                    'acc_type'      => 'customer',
                    'iid'           => ($type === 'return') ? 'CUR' : 'INV',
                    'doc_no'        => $invNumber,
                    'transaction_amount' => $netAmount,
                    'document_date' => $data['document_date'] ?? now(),
                    'month_end'     => 0,
                    'balance_amount' => $balanceAmount,
                ]);
            }

            // ---------------------------------------------------------
            // 4. Move Products (Temp -> Actual) & Update Stock
            // ---------------------------------------------------------
            $products = TempTransactionSaleDetail::where('doc_no', $data['doc_no'])->get();

            if ($products->isNotEmpty()) {
                foreach ($products as $product) {
                    // 4.1 Create Transaction Detail
                    TransactionSaleDetail::create([
                        'transaction_sale_header_id' => $transactionSaleHeader->id,
                        'doc_no'        => $invNumber,
                        'line_no'       => $product->line_no,
                        'iid'           => 'INV',
                        'amount'        => abs($product->amount),
                        'prod_code'     => $product->prod_code,
                        'prod_name'     => $product->prod_name,
                        'type'          => $product->type ?? 'Sales',
                        'purchase_price' => $product->purchase_price,
                        'marked_price'  => $product->marked_price ?? 0,
                        'selling_price' => $product->selling_price,
                        'line_wise_discount_value' => $product->line_wise_discount_value,
                        'free_qty'      => abs($product->free_qty),
                        'unit_qty'      => abs($product->unit_qty),
                        'pack_qty'      => abs($product->pack_qty),
                        'total_qty'     => abs($product->total_qty),
                    ]);

                    // 4.2 Update Stock Master
                    // Sales = Outgoing (-), Return = Incoming (+)
                    $qtyChange = -abs($product->total_qty);
                    $amountChange = -abs($product->amount); // Stock Value Impact

                    if ($type === 'return' || ($product->type ?? 'Sales') === 'Return') {
                        $qtyChange = abs($product->total_qty);
                        $amountChange = abs($product->amount);
                    }

                    StockMaster::create([
                        'location'      => $data['location'],
                        'transaction_date' => $data['document_date'] ?? now(),
                        'doc_no'        => $invNumber,
                        'prod_code'     => $product->prod_code,
                        'iid'           => 'INV',
                        'qty'           => $qtyChange,
                        'purchase_price' => $product->purchase_price,
                        'selling_price' => $product->selling_price,
                        'amount'        => $amountChange,
                    ]);

                    // 4.3 Product Sale Summary
                    ProductSaleSummary::create([
                        'location'      => $data['location'],
                        'doc_no'        => $invNumber,
                        'iid'           => 'INV',
                        'free_qty'      => abs($product->free_qty),
                        'product_code'  => $product->prod_code,
                        'product_name'  => $product->prod_name,
                        'pack_qty'      => abs($product->pack_qty),
                        'selling_price' => $product->selling_price,
                        'purchase_price' => $product->purchase_price,
                        'sale_date'     => $data['document_date'] ?? now(),
                        'amount'        => abs($product->amount),
                    ]);
                }
            }

            // ---------------------------------------------------------
            // 5. Cleanup Temp Data
            // ---------------------------------------------------------
            TempTransactionSaleDetail::where('doc_no', $data['doc_no'])->delete();
            TempTransactionSaleHeader::where('doc_no', $data['doc_no'])->delete();

            DB::commit();

            return response()->json([
                'type' => 'success',
                'message' => 'Invoice Created Successfully!',
                'data' => [
                    'doc_no' => $invNumber,
                    'header_id' => $transactionSaleHeader->id
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'type' => 'error',
                'message' => 'Failed to create invoice: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function loadInvoiceByCode($doc_number, $status, $iid)
    {
        if ($status == 'applied') {
            $transactionHeaders = TransactionSaleHeader::with([
                'customer',
                'location',
                'deliveryLocation',
                'transactionSaleDetails.product.unit',
                'transactionSaleDetails' => function ($query) {
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
            $tempTransactionHeaders = TempTransactionSaleHeader::with([
                'customer',
                'location',
                'deliveryLocation',
                'tempTransactionSaleDetails.product.unit',
                'tempTransactionSaleDetails' => function ($query) {
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

    public function loadVatInvoiceByCode($doc_number, $status, $iid)
    {
        if ($status == 'applied') {
            $transactionHeaders = TransactionSaleHeader::with([
                'customer',
                'location',
                'deliveryLocation',
                'transactionSaleDetails.product.unit',
                'transactionSaleDetails' => function ($query) {
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
            $tempTransactionHeaders = TempTransactionSaleHeader::with([
                'customer',
                'location',
                'deliveryLocation',
                'tempTransactionSaleDetails.product.unit',
                'tempTransactionSaleDetails' => function ($query) {
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

    public function getCompanyHeader()
    {
        try {
            $company = CompanyHeader::first();
            return response()->json([
                'success' => true,
                'data' => $company
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch company details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
