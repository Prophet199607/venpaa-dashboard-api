<?php

namespace App\Http\Controllers\Payment;

use App\Models\DocNumber;
use Illuminate\Http\Request;
use App\Models\PaymentSummary;
use App\Models\PaidPaymentDetail;
use App\Models\PaidPaymentSummary;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
                'last_id' => 0,
            ]
        );

        $number = $doc->prefix
            . $loca
            . str_pad($doc->last_id + 1, 8, '0', STR_PAD_LEFT);

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
            ->whereIn('iid', ['SADV', 'SRN'])
            ->where('balance_amount', '>', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function storeVoucher(Request $request)
    {
        DB::beginTransaction();
        try {

            $org_doc_num = $request->receipt['doc_no'];
            $over_payment = $request->receipt['over_payment'];

            $payments = $request->input('payments', []);

            // Check if it's multiple payments (array of arrays) or single payment (single array)
            if (isset($payments[0]) && is_array($payments[0])) {
                // Multiple payments - get mode from first payment
                $set_off = $payments[0]['mode'] ?? '';
            } else {
                // Single payment - get mode directly
                $set_off = $payments['mode'] ?? '';
            }

            // Get setoff documents
            $set_offs = $request->input('setoff.selectedDocs', []);

            // Validation: If payment mode is "PAYMENT SETOFF", setoff documents must exist
            if ($set_off == "PAYMENT SETOFF" && empty($set_offs)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Payment setoff mode requires at least one setoff document to be selected.',
                ], 422);
            }


            do {
                // Check if doc_no exists in either table
                $existsInSummary = PaidPaymentSummary::where('org_doc_no', $org_doc_num)->exists();
                $existsInDetail  = PaidPaymentDetail::where('org_doc_no', $org_doc_num)->exists();

                if ($existsInSummary || $existsInDetail) {
                    // Split last 8 digits and prefix
                    $prefix = substr($org_doc_num, 0, -8);
                    $lastPart = substr($org_doc_num, -8);

                    // Increment numeric part
                    $newNumber = (int)$lastPart + 1;

                    // Re-pad to 8 digits
                    $lastPart = str_pad($newNumber, 8, '0', STR_PAD_LEFT);

                    // Rebuild new org_doc_no
                    $org_doc_num = $prefix . $lastPart;
                }
            } while ($existsInSummary || $existsInDetail);

            // Update last_id in DocNumber table (type: Receipt, prefix: REC) to match the last 8 digits of $org_doc_num
            $last8Digits = (int)substr($org_doc_num, -8);
            DocNumber::where('type', 'Payment')
                ->where('prefix', 'PMT')
                ->update(['last_id' => $last8Digits]);

            $allocations = $request->input('allocations', []);
            //Generate setoff number if needed
            $setoffNumber = "";
            if ($set_off == "PAYMENT SETOFF") {
                $doc = DocNumber::where('type', 'SupplierSetOff')->first();

                if (!$doc) {
                    $doc = DocNumber::create([
                        'type' => 'SupplierSetOff',
                        'prefix' => 'SSOF',
                        'last_id' => 1
                    ]);
                } else {
                    $doc->last_id += 1;
                    $doc->save();
                }

                $setoffNumber = $doc->prefix . $request->receipt['location'] . str_pad($doc->last_id, 8, '0', STR_PAD_LEFT);

                // update into PaymentSummary for setoff
                foreach ($set_offs as $set_off) {
                    PaymentSummary::where('doc_no', $set_off['doc_no'])->update(['balance_amount' => $set_off['balance_amount'] - $set_off['paid_amount']]);

                    PaidPaymentDetail::insert([
                        'industry_code' => auth()->user()->industry_code,
                        'org_doc_no' => $setoffNumber,
                        'doc_no' => $set_off['doc_no'],
                        'location' => $request->receipt['location'],
                        'transaction_amount' => $set_off['transaction_amount'],
                        'transaction_date' => $request->receipt['date'],
                        'balance_amount' => $set_off['balance_amount'],
                        'paid_amount' => $set_off['paid_amount'],
                        'iid' => "SSOF",
                        'acc_code' => $request->supplier['supplier_code'],
                        'document_date' => $request->receipt['date'],
                        'temp_doc_no' => "",
                        'setoff_sr_doc' => $org_doc_num,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Update each PaymentSummary record instead of insert
            foreach ($allocations as $allocation) {
                // Fetch the current PaymentSummary
                $paymentSummary = PaymentSummary::where('doc_no', $allocation['doc_no'])->first();

                // Check if balance_amount is not zero before updating
                if ($paymentSummary && $paymentSummary->balance_amount != 0) {
                    $paid = isset($allocation['paid_amount']) ? $allocation['paid_amount'] : 0;
                    $paymentSummary->update([
                        'balance_amount' => $allocation['balance_amount'] - $paid,
                    ]);
                }
            }

            // Iterate over allocations and receipt using array_map to pair corresponding elements.
            foreach ($allocations as $allocation) {
                PaidPaymentDetail::insert([
                    'industry_code' => auth()->user()->industry_code,
                    'org_doc_no' => $org_doc_num,
                    'doc_no' => $allocation['doc_no'],
                    'location' => $request->receipt['location'],
                    'transaction_amount' => $allocation['transaction_amount'],
                    'transaction_date' => $request->receipt['date'],
                    'balance_amount' => $allocation['balance_amount'],
                    'paid_amount' => isset($allocation['paid_amount']) ? $allocation['paid_amount'] : 0,
                    'iid' => "PMT",
                    'acc_code' => $request->supplier['supplier_code'],
                    'document_date' => $request->receipt['date'],
                    'temp_doc_no' => "",
                    'setoff_sr_doc' => $setoffNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Handle multiple payments - each allocation paired with each payment creates a record
            if (isset($payments[0]) && is_array($payments[0])) {
                // Multiple payments case
                foreach ($allocations as $allocation) {
                    foreach ($payments as $payment) {
                        PaidPaymentSummary::insert([
                            'industry_code' => auth()->user()->industry_code,
                            'temp_doc_no' => "",
                            'org_doc_no' => $org_doc_num,
                            'doc_no' => $allocation['doc_no'],
                            'location' => $request->receipt['location'],
                            'payment_mode' => $payment['mode'],
                            'bank_name' => $payment['bank'],
                            'cheque_no' => $payment['chequeNo'],
                            'cheque_date' => $payment['chequeDate'],
                            'branch' => $payment['branch'],
                            'amount' => isset($payment['amount']) ? $payment['amount'] : (isset($payment['paid_amount']) ? $payment['paid_amount'] : 0),
                            'iid' => "PMT",
                            'acc_code' => $request->supplier['supplier_code'],
                            'transaction_date' => $request->receipt['date'],
                            'document_date' => $request->receipt['date'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            } else {
                // Single payment case (your existing logic)
                foreach ($allocations as $allocation) {
                    PaidPaymentSummary::insert([
                        'industry_code' => auth()->user()->industry_code,
                        'temp_doc_no' => "",
                        'org_doc_no' => $org_doc_num,
                        'doc_no' => $allocation['doc_no'],
                        'location' => $request->receipt['location'],
                        'payment_mode' => $payments['mode'],
                        'bank_name' => $payments['bank'],
                        'cheque_no' => $payments['chequeNo'],
                        'cheque_date' => $payments['chequeDate'],
                        'branch' => $payments['branch'],
                        'amount' => isset($payments['amount']) ? $payments['amount'] : (isset($payments['paid_amount']) ? $payments['paid_amount'] : 0),
                        'iid' => "PMT",
                        'acc_code' => $request->supplier['supplier_code'],
                        'transaction_date' => $request->receipt['date'],
                        'document_date' => $request->receipt['date'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Handle over payment: if $over_payment < 0, record in PaidPaymentSummary and PaymentSummary as described
            if ($over_payment < 0) {
                // Prepare values
                $op_amount = abs($over_payment);

                // Save a new PaymentSummary
                PaymentSummary::insert([
                    'industry_code' => auth()->user()->industry_code,
                    'doc_no' => $org_doc_num,
                    'transaction_amount' => $op_amount,
                    'acc_code' => $request->supplier['supplier_code'],
                    'acc_type' => 'supplier',
                    'month_end' => 0,
                    'iid' => "OVPMT",
                    'balance_amount' => $op_amount,
                    'transaction_date' => $request->receipt['date'],
                    'document_date' => $request->receipt['date'],
                    'location' => $request->receipt['location'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Save a new PaidPaymentSummary
                PaidPaymentSummary::insert([
                    'industry_code' => auth()->user()->industry_code,
                    'temp_doc_no' => "",
                    'org_doc_no' => $org_doc_num,
                    'doc_no' => $org_doc_num,
                    'location' => $request->receipt['location'],
                    'payment_mode' => $payment['mode'],
                    'bank_name' => $payment['bank'],
                    'cheque_no' => $payment['chequeNo'],
                    'cheque_date' => $payment['chequeDate'],
                    'branch' => $payment['branch'],
                    'amount' => $op_amount,
                    'iid' => "PMT",
                    'acc_code' => $request->supplier['supplier_code'],
                    'transaction_date' => $request->receipt['date'],
                    'document_date' => $request->receipt['date'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Payment summary balance amount updated successfully.',
                'org_doc_no' => $org_doc_num,
                'setoff_number' => $setoffNumber ?: null,
                'has_setoff' => !empty($setoffNumber),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error : ' . $e->getMessage(),
            ], 500);
        }
    }
}
