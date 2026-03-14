<?php

namespace App\Http\Controllers\Payment;

use App\Models\DocNumber;
use Illuminate\Http\Request;
use App\Models\PaymentSummary;
use App\Models\PaidPaymentDetail;
use App\Models\PaidPaymentSummary;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\PaymentVoucherRequest;
use App\Http\Requests\Payment\CustomerReceiptRequest;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Http\Resources\Payment\PaidPaymentSummaryResource;
use App\Models\Supplier;
use App\Models\Customer;

class PaymentVoucherController extends Controller
{
    use HasFactory;
    protected $guarded = [];

    public function getPmtNumber(Request $request)
    {
        $request->validate([
            'loca' => 'required|string|max:5',
        ]);

        $loca = $request->loca;

        $doc = DocNumber::firstOrCreate(
            ['type' => 'Payment'],
            [
                'prefix' => 'PMT',
                'length' => 8,
                'last_id' => 0,
            ]
        );

        $number = $doc->prefix
            . $loca
            . str_pad($doc->last_id + 1, $doc->length, '0', STR_PAD_LEFT);

        return response()->json([
            'success' => true,
            'message' => 'Code generated successfully',
            'code' => $number,
        ]);
    }

    public function getPendingPaymentsVoucher($supplier_code, $loca_code, $iid)
    {
        $payments = PaymentSummary::where('acc_type', 'supplier')
            ->where('acc_code', $supplier_code)
            ->where('location', $loca_code)
            ->where('iid', $iid)
            ->where('balance_amount', '>', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    public function getAvailableSetOffs($supplier_code, $loca_code)
    {
        $data = PaymentSummary::where('acc_type', 'supplier')
            ->where('acc_code', $supplier_code)
            ->where('location', $loca_code)
            ->whereIn('iid', ['SADV', 'SRN', 'OVPMT'])
            ->where('balance_amount', '>', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function store(PaymentVoucherRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            $receipt = $validated['receipt'];
            $supplier = $validated['supplier'];
            $payments = $validated['payments'];
            $allocations = $validated['allocations'];
            $setOffDocs = $validated['setoff']['selectedDocs'] ?? [];

            $orgDocNo = $receipt['doc_no'];
            $location = $receipt['location'];
            $date = $receipt['date'];
            $overPayment = (float) $receipt['over_payment'];
            $supplierCode = $supplier['supplier_code'];

            // 1. Ensure Unique Document Number
            while (
                PaidPaymentSummary::where('org_doc_no', $orgDocNo)->exists() ||
                PaidPaymentDetail::where('org_doc_no', $orgDocNo)->exists()
            ) {
                $prefix = substr($orgDocNo, 0, -8);
                $lastPart = substr($orgDocNo, -8);
                $newNumber = (int) $lastPart + 1;
                $lastPart = str_pad($newNumber, 8, '0', STR_PAD_LEFT);
                $orgDocNo = $prefix . $lastPart;
            }

            // 2. Update Payment Doc Number Sequence
            $last8Digits = (int) substr($orgDocNo, -8);
            DocNumber::where('type', 'Payment')
                ->where('prefix', 'PMT')
                ->update(['last_id' => $last8Digits]);

            // 3. Handle Payment Set-Off
            $setoffNumber = "";
            $hasSetOffPayment = collect($payments)->contains(function ($p) {
                return isset($p['mode']) && strtoupper($p['mode']) === 'PAYMENT SETOFF';
            });

            if ($hasSetOffPayment) {
                if (empty($setOffDocs)) {
                    throw new \Exception('Payment setoff mode requires at least one setoff document.');
                }

                $setoffNumber = DocNumber::generate('SupplierSetOff', 'CSOF', 8, $location);

                foreach ($setOffDocs as $doc) {
                    $paidAmount = (float) $doc['paid_amount'];

                    // Decrement balance in PaymentSummary
                    PaymentSummary::where('doc_no', $doc['doc_no'])
                        ->decrement('balance_amount', $paidAmount);

                    // Record in PaidPaymentDetail
                    PaidPaymentDetail::create([
                        'org_doc_no' => $setoffNumber,
                        'doc_no' => $doc['doc_no'],
                        'location' => $location,
                        'transaction_amount' => $doc['transaction_amount'],
                        'transaction_date' => $date,
                        'balance_amount' => $doc['balance_amount'],
                        'paid_amount' => $paidAmount,
                        'iid' => "CSOF",
                        'acc_code' => $supplierCode,
                        'document_date' => $date,
                        'temp_doc_no' => "",
                        'setoff_sr_doc' => $orgDocNo,
                    ]);
                }
            }

            // 4. Process Bill Allocations
            foreach ($allocations as $allocation) {
                $paid = (float) $allocation['paid_amount'];

                // Update original bill balance
                PaymentSummary::where('doc_no', $allocation['doc_no'])
                    ->where('balance_amount', '!=', 0)
                    ->decrement('balance_amount', $paid);

                // Record detail of what bill was paid
                PaidPaymentDetail::create([
                    'org_doc_no' => $orgDocNo,
                    'doc_no' => $allocation['doc_no'],
                    'location' => $location,
                    'transaction_amount' => $allocation['transaction_amount'],
                    'transaction_date' => $date,
                    'balance_amount' => $allocation['balance_amount'],
                    'paid_amount' => $paid,
                    'iid' => "PMT",
                    'acc_code' => $supplierCode,
                    'document_date' => $date,
                    'temp_doc_no' => "",
                    'setoff_sr_doc' => $setoffNumber,
                ]);

                // Record payment mode breakdown for each allocation
                foreach ($payments as $payment) {
                    PaidPaymentSummary::create([
                        'temp_doc_no' => "",
                        'org_doc_no' => $orgDocNo,
                        'doc_no' => $allocation['doc_no'],
                        'location' => $location,
                        'payment_mode' => $payment['mode'],
                        'bank_name' => $payment['bank'] ?? null,
                        'cheque_no' => $payment['chequeNo'] ?? null,
                        'cheque_date' => $payment['chequeDate'] ?? null,
                        'branch' => $payment['branch'] ?? null,
                        'amount' => (float) ($payment['amount'] ?? 0),
                        'iid' => "PMT",
                        'acc_code' => $supplierCode,
                        'transaction_date' => $date,
                        'document_date' => $date,
                    ]);
                }
            }

            // 5. Handle Over-Payment (Unallocated Cash/Credit)
            if ($overPayment < 0) {
                $opAmount = abs($overPayment);

                // Create a new credit record for the supplier (Advance/Overpayment)
                PaymentSummary::create([
                    'doc_no' => $orgDocNo,
                    'transaction_amount' => $opAmount,
                    'acc_code' => $supplierCode,
                    'acc_type' => 'supplier',
                    'month_end' => 0,
                    'iid' => "OVPMT",
                    'balance_amount' => $opAmount,
                    'transaction_date' => $date,
                    'document_date' => $date,
                    'location' => $location,
                ]);

                // Record the payment that caused the overpayment
                $primaryPayment = $payments[0];
                PaidPaymentSummary::create([
                    'temp_doc_no' => "",
                    'org_doc_no' => $orgDocNo,
                    'doc_no' => $orgDocNo,
                    'location' => $location,
                    'payment_mode' => $primaryPayment['mode'],
                    'bank_name' => $primaryPayment['bank'] ?? null,
                    'cheque_no' => $primaryPayment['chequeNo'] ?? null,
                    'cheque_date' => $primaryPayment['chequeDate'] ?? null,
                    'branch' => $primaryPayment['branch'] ?? null,
                    'amount' => $opAmount,
                    'iid' => "PMT",
                    'acc_code' => $supplierCode,
                    'transaction_date' => $date,
                    'document_date' => $date,
                ]);
            }

            $createdPayments = PaidPaymentSummary::where('org_doc_no', $orgDocNo)->get();

            return response()->json([
                'success' => true,
                'message' => 'Payment voucher created successfully.',
                'org_doc_no' => $orgDocNo,
                'setoff_number' => $setoffNumber,
                'has_setoff' => !empty($setoffNumber),
                'data' => PaidPaymentSummaryResource::collection($createdPayments),
            ]);
        });
    }

    public function getRecNumber(Request $request)
    {
        $request->validate([
            'loca' => 'required|string|max:5',
        ]);

        $loca = $request->loca;

        $doc = DocNumber::firstOrCreate(
            ['type' => 'Receipt'],
            [
                'prefix' => 'REC',
                'length' => 8,
                'last_id' => 0,
            ]
        );

        $number = $doc->prefix
            . $loca
            . str_pad($doc->last_id + 1, $doc->length, '0', STR_PAD_LEFT);

        return response()->json([
            'success' => true,
            'message' => 'Code generated successfully',
            'code' => $number,
        ]);
    }

    public function getPendingCustomerReceipts($customer_code, $loca_code, $iid)
    {
        $receipts = PaymentSummary::where('acc_type', 'customer')
            ->where('acc_code', $customer_code)
            ->where('location', $loca_code)
            ->where('iid', $iid)
            ->where('balance_amount', '>', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $receipts
        ]);
    }

    public function getAvailableSetOffsRec($customer_code, $loca_code)
    {
        $data = PaymentSummary::where('acc_type', 'customer')
            ->where('acc_code', $customer_code)
            ->where('location', $loca_code)
            ->whereIn('iid', ['CADV', 'CUR', 'OVREC'])
            ->where('balance_amount', '>', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function receiptStore(CustomerReceiptRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            $receipt = $validated['receipt'];
            $customer = $validated['customer'];
            $payments = $validated['payments'];
            $allocations = $validated['allocations'];
            $setOffDocs = $validated['setoff']['selectedDocs'] ?? [];

            $orgDocNo = $receipt['doc_no'];
            $location = $receipt['location'];
            $date = $receipt['date'];
            $overPayment = (float) $receipt['over_payment'];
            $customerCode = $customer['customer_code'];

            // 1. Ensure Unique Document Number
            while (
                PaidPaymentSummary::where('org_doc_no', $orgDocNo)->exists() ||
                PaidPaymentDetail::where('org_doc_no', $orgDocNo)->exists()
            ) {
                $prefix = substr($orgDocNo, 0, -8);
                $lastPart = substr($orgDocNo, -8);
                $newNumber = (int) $lastPart + 1;
                $lastPart = str_pad($newNumber, 8, '0', STR_PAD_LEFT);
                $orgDocNo = $prefix . $lastPart;
            }

            // 2. Update Payment Doc Number Sequence
            $last8Digits = (int) substr($orgDocNo, -8);
            DocNumber::where('type', 'Receipt')
                ->where('prefix', 'REC')
                ->update(['last_id' => $last8Digits]);

            // 3. Handle Payment Set-Off
            $setoffNumber = "";
            $hasSetOffPayment = collect($payments)->contains(function ($p) {
                return isset($p['mode']) && strtoupper($p['mode']) === 'PAYMENT SETOFF';
            });

            if ($hasSetOffPayment) {
                if (empty($setOffDocs)) {
                    throw new \Exception('Payment setoff mode requires at least one setoff document.');
                }

                $setoffNumber = DocNumber::generate('CustomerSetOff', 'CSOF', 8, $location);

                foreach ($setOffDocs as $doc) {
                    $paidAmount = (float) $doc['paid_amount'];

                    // Decrement balance in PaymentSummary
                    PaymentSummary::where('doc_no', $doc['doc_no'])
                        ->decrement('balance_amount', $paidAmount);

                    // Record in PaidPaymentDetail
                    PaidPaymentDetail::create([
                        'org_doc_no' => $setoffNumber,
                        'doc_no' => $doc['doc_no'],
                        'location' => $location,
                        'transaction_amount' => $doc['transaction_amount'],
                        'transaction_date' => $date,
                        'balance_amount' => $doc['balance_amount'],
                        'paid_amount' => $paidAmount,
                        'iid' => "CSOF",
                        'acc_code' => $customerCode,
                        'document_date' => $date,
                        'temp_doc_no' => "",
                        'setoff_sr_doc' => $orgDocNo,
                    ]);
                }
            }

            // 4. Process Bill Allocations
            foreach ($allocations as $allocation) {
                $paid = (float) $allocation['paid_amount'];

                // Update original bill balance
                PaymentSummary::where('doc_no', $allocation['doc_no'])
                    ->where('balance_amount', '!=', 0)
                    ->decrement('balance_amount', $paid);

                // Record detail of what bill was paid
                PaidPaymentDetail::create([
                    'org_doc_no' => $orgDocNo,
                    'doc_no' => $allocation['doc_no'],
                    'location' => $location,
                    'transaction_amount' => $allocation['transaction_amount'],
                    'transaction_date' => $date,
                    'balance_amount' => $allocation['balance_amount'],
                    'paid_amount' => $paid,
                    'iid' => "REC",
                    'acc_code' => $customerCode,
                    'document_date' => $date,
                    'temp_doc_no' => "",
                    'setoff_sr_doc' => $setoffNumber,
                ]);

                // Record payment mode breakdown for each allocation
                foreach ($payments as $payment) {
                    PaidPaymentSummary::create([
                        'temp_doc_no' => "",
                        'org_doc_no' => $orgDocNo,
                        'doc_no' => $allocation['doc_no'],
                        'location' => $location,
                        'payment_mode' => $payment['mode'],
                        'bank_name' => $payment['bank'] ?? null,
                        'cheque_no' => $payment['chequeNo'] ?? null,
                        'cheque_date' => $payment['chequeDate'] ?? null,
                        'branch' => $payment['branch'] ?? null,
                        'amount' => (float) ($payment['amount'] ?? 0),
                        'iid' => "REC",
                        'acc_code' => $customerCode,
                        'transaction_date' => $date,
                        'document_date' => $date,
                    ]);
                }
            }

            // 5. Handle Over-Payment (Unallocated Cash/Credit)
            if ($overPayment < 0) {
                $opAmount = abs($overPayment);

                // Create a new credit record for the suppr (Advance/Overpayment)
                PaymentSummary::create([
                    'doc_no' => $orgDocNo,
                    'transaction_amount' => $opAmount,
                    'acc_code' => $customerCode,
                    'acc_type' => 'customer',
                    'month_end' => 0,
                    'iid' => "OVREC",
                    'balance_amount' => $opAmount,
                    'transaction_date' => $date,
                    'document_date' => $date,
                    'location' => $location,
                ]);

                // Record the payment that caused the overpayment
                $primaryPayment = $payments[0];
                PaidPaymentSummary::create([
                    'temp_doc_no' => "",
                    'org_doc_no' => $orgDocNo,
                    'doc_no' => $orgDocNo,
                    'location' => $location,
                    'payment_mode' => $primaryPayment['mode'],
                    'bank_name' => $primaryPayment['bank'] ?? null,
                    'cheque_no' => $primaryPayment['chequeNo'] ?? null,
                    'cheque_date' => $primaryPayment['chequeDate'] ?? null,
                    'branch' => $primaryPayment['branch'] ?? null,
                    'amount' => $opAmount,
                    'iid' => "REC",
                    'acc_code' => $customerCode,
                    'transaction_date' => $date,
                    'document_date' => $date,
                ]);
            }

            $createdPayments = PaidPaymentSummary::where('org_doc_no', $orgDocNo)->get();

            return response()->json([
                'success' => true,
                'message' => 'Customer receipt created successfully.',
                'org_doc_no' => $orgDocNo,
                'setoff_number' => $setoffNumber,
                'has_setoff' => !empty($setoffNumber),
                'data' => PaidPaymentSummaryResource::collection($createdPayments),
            ]);
        });
    }

    public function loadAllPaymentVouchers(Request $request)
    {
        return $this->loadAll('PMT', $request);
    }

    public function loadAllCustomerReceipts(Request $request)
    {
        return $this->loadAll('REC', $request);
    }

    private function loadAll($iid, Request $request)
    {
        try {
            $user = $request->user();
            $location = $request->input('location') ?: (isset($user->location) ? $user->location : null);
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $perPage = $request->input('per_page', 10);

            // Using PaidPaymentSummary to get unique vouchers and totals
            // We group by org_doc_no, acc_code, location, document_date to get unique headers
            // For the total amount, we sum paid_amount from PaidPaymentDetail because 
            // PaidPaymentSummary might have repeated amounts per allocation.

            $query = PaidPaymentDetail::where('iid', $iid);

            if ($location) {
                $query->where('location', $location);
            }
            if ($startDate) {
                $query->whereDate('document_date', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('document_date', '<=', $endDate);
            }

            $results = $query->select('org_doc_no', 'document_date', 'acc_code', 'location')
                ->selectRaw('SUM(paid_amount) as total_amount')
                ->groupBy('org_doc_no', 'document_date', 'acc_code', 'location')
                ->orderBy('document_date', 'desc')
                ->paginate($perPage);

            $formattedData = collect($results->items())->map(function ($item) use ($iid) {
                $data = $item->toArray();
                if ($iid === 'PMT' || $iid === 'PMTV') {
                    $supplier = Supplier::where('sup_code', $item->acc_code)->first();
                    $data['account_name'] = $supplier ? $supplier->sup_name : 'N/A';
                } else {
                    $customer = Customer::where('customer_code', $item->acc_code)->first();
                    $data['account_name'] = $customer ? $customer->customer_name : 'N/A';
                }
                return $data;
            });

            return response()->json([
                'success' => true,
                'message' => 'Transactions loaded successfully!',
                'data' => $formattedData,
                'meta' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'total' => $results->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
