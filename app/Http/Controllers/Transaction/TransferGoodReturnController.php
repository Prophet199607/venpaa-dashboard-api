<?php

namespace App\Http\Controllers\Transaction;

use App\Models\DocNumber;
use App\Models\StockMaster;
use Illuminate\Http\Request;
use App\Models\TransactionDetail;
use App\Models\TransactionHeader;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\TempTransactionDetail;
use App\Models\TempTransactionHeader;
use App\Http\Requests\Transaction\TempTransactionDetailRequest;
use App\Http\Requests\Transaction\TempTransactionHeaderRequest;
class TransferGoodReturnController extends Controller
{
    public function store(TempTransactionHeaderRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->validated();
                $tgrNumber = DocNumber::generate('TGR', 'TGR', 8, $data['location']);

                $headerData = $data;
                unset($headerData['id']);
                $transactionHeader = TransactionHeader::create(array_merge($headerData, [
                    'doc_no'      => $tgrNumber,
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
                        'doc_no'                => $tgrNumber,
                    ]));
                    $transactionDetails[] = $transactionDetail;
                }

                // Create StockMaster records for each product
                foreach ($transactionDetails as $detail) {
                    StockMaster::create([
                        'location' => $data['location'],
                        'transaction_date' => $data['document_date'],
                        'doc_no' => $tgrNumber,
                        'prod_code' => $detail->prod_code,
                        'iid' => $data['iid'] ?? 'TGR',
                        'qty' => $detail->total_qty ?? 0.000,
                        'purchase_price' => $detail->purchase_price ?? 0.00,
                        'selling_price' => $detail->selling_price ?? 0.00,
                        'amount' => $detail->amount ?? 0.00,
                        'created_at' => now(),
                        'updated_at' => now(),
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
                    'message' => 'Transfer good return stored successfully.',
                    'data'    => $transactionHeader->fresh(),
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to store transfer good return.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
