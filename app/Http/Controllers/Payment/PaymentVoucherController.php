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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Http\Resources\Payment\PaidPaymentSummaryResource;

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
            $hasSetOffPayment = collect($payments)->contains('mode', 'PAYMENT SETOFF');

            if ($hasSetOffPayment) {
                if (empty($setOffDocs)) {
                    throw new \Exception('Payment setoff mode requires at least one setoff document.');
                }

                $setoffNumber = DocNumber::generate('SupplierSetOff', 'SSOF', 8, $location);

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
                        'iid' => "SSOF",
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
}
