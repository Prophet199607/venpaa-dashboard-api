<?php

namespace App\Http\Controllers\Transaction;

use Carbon\Carbon;
use App\Models\Product;
use App\Models\Location;
use App\Models\DocNumber;
use App\Models\StockMaster;
use App\Models\PaymentSummary;
use App\Models\PaidPaymentDetail;
use App\Models\TransactionDetail;
use App\Models\TransactionHeader;
use App\Models\PaidPaymentSummary;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\TempTransactionDetail;
use App\Models\TempTransactionHeader;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\Transaction\TempTransactionHeaderRequest;

class GoodReceiveNoteController extends Controller
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

    public function getUnsavedSessions()
    {
        try {
            $unsavedSessions = TempTransactionDetail::where('created_by', auth()->id())
                ->where('iid', 'GRN')
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
                $cacheKey = 'po_loaded_grn_' . $doc_no;

                $poMetadata = Cache::get($cacheKey);

                if (!$poMetadata) {
                    $filteredSessions[] = $doc_no;
                } else {
                    // This is a PO-loaded session, clean it up if it's old (more than 10 minutes)
                    $loadedTime = Carbon::parse($poMetadata['loaded_at']);
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

    public function store(TempTransactionHeaderRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $this->processDiscountAndTax($request->validated());
                $grnNumber = DocNumber::generate('GRN', 'GRN', 8, $data['location']);

                $headerData = $data;
                unset($headerData['id']);
                $transactionHeader = TransactionHeader::create(array_merge($headerData, [
                    'doc_no'      => $grnNumber,
                    'temp_doc_no' => $data['doc_no'],
                    'created_by'  => auth()->id(),
                ]));

                // Load temp products for this temp doc
                $tempProducts = TempTransactionDetail::where('doc_no', $data['doc_no'])
                    ->orderBy('line_no')
                    ->get();

                $transactionDetails = [];
                foreach ($tempProducts as $temp) {
                    $tempData = $temp->toArray();
                    unset($tempData['temp_transaction_header_id'], $tempData['id']);
                    $transactionDetail = TransactionDetail::create(array_merge($tempData, [
                        'transaction_header_id' => $transactionHeader->id,
                        'doc_no'                => $grnNumber,
                    ]));
                    $transactionDetails[] = $transactionDetail;
                }

                // Create StockMaster records for each product
                foreach ($transactionDetails as $detail) {
                    // Update Product prices if unconfirmed
                    $product = Product::where('prod_code', $detail->prod_code)->first();
                    if ($product && $product->unconfirm_price == 1) {
                        $product->update([
                            'purchase_price' => $detail->purchase_price ?? $product->purchase_price,
                            'selling_price'  => $detail->selling_price ?? $product->selling_price,
                            'unconfirm_price' => 0,
                        ]);
                    }

                    StockMaster::create([
                        'location' => $data['location'],
                        'transaction_date' => $data['transaction_date'],
                        'doc_no' => $grnNumber,
                        'prod_code' => $detail->prod_code,
                        'iid' => $data['iid'] ?? 'GRN',
                        'qty' => $detail->total_qty ?? 0.000,
                        'purchase_price' => $detail->purchase_price ?? 0.00,
                        'selling_price' => $detail->selling_price ?? 0.00,
                        'amount' => $detail->amount ?? 0.00,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Persist payment information in summary/detail tables
                $netTotal = $data['net_total'] ?? 0;
                $paymentMode = strtolower($data['payment_mode'] ?? '');
                $iid = $netTotal < 0 ? 'SRN' : ($data['iid'] ?? 'GRN');
                $paymentDocNo = DocNumber::generate('Payment', 'PMT', 8, $data['location']);

                PaymentSummary::create([
                    'acc_code' => $data['supplier_code'] ?? null,
                    'acc_type' => 'supplier',
                    'iid' => $iid,
                    'doc_no' => $grnNumber,
                    'transaction_amount' => abs($netTotal),
                    'transaction_date' => $data['transaction_date'] ?? null,
                    'document_date' => $data['document_date'] ?? null,
                    'location' => $data['location'] ?? null,
                    'month_end' => 0,
                    'balance_amount' => $netTotal < 0 ? abs($netTotal) : ($paymentMode === 'credit' ? $netTotal : 0),
                ]);

                // Only create immediate payment records when not credit
                if ($netTotal >= 0 && $paymentMode !== 'credit') {
                    PaidPaymentDetail::create([
                        'org_doc_no' => $paymentDocNo,
                        'doc_no' => $grnNumber,
                        'transaction_amount' => abs($netTotal),
                        'transaction_date' => $data['transaction_date'] ?? null,
                        'balance_amount' => 0,
                        'paid_amount' => $netTotal,
                        'temp_doc_no' => $data['doc_no'] ?? null,
                        'location' => $data['location'] ?? null,
                        'iid' => $iid === 'SRN' ? 'SRN' : 'PMT',
                        'acc_code' => $data['supplier_code'] ?? null,
                        'document_date' => $data['document_date'] ?? null,
                        'setoff_sr_doc' => 0,
                    ]);

                    PaidPaymentSummary::create([
                        'temp_doc_no' => $data['doc_no'] ?? null,
                        'org_doc_no' => $paymentDocNo,
                        'doc_no' => $grnNumber,
                        'payment_mode' => $data['payment_mode'] ?? null,
                        'amount' => abs($netTotal),
                        'location' => $data['location'] ?? null,
                        'iid' => $iid === 'SRN' ? 'SRN' : 'PMT',
                        'acc_code' => $data['supplier_code'] ?? null,
                        'transaction_date' => $data['transaction_date'] ?? null,
                        'document_date' => $data['document_date'] ?? null,
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
                    'message' => 'Good receive note stored successfully.',
                    'data'    => $transactionHeader->fresh(),
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to store good receive note.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
