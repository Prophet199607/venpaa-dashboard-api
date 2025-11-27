<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Product;
use App\Models\Location;
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
use App\Http\Resources\Transaction\TempTransactionDetailResource;
use App\Http\Resources\Transaction\TempTransactionHeaderResource;

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

    public function getTempGrnNumber($loca_code)
    {
        try {
            $docCode = DocNumber::generate('TempGRN', 'GRN', 8, $loca_code);

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
            $supplier = $product?->suppliers->first();
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

    public function getAllPOProducts(Request $request)
    {
        DB::beginTransaction();
        try {
            $poDocNumber = $request->input('doc_number');
            $grnDocNumber = $request->input('grn_number');
            $iid = $request->input('iid');

            if (!$poDocNumber || !$grnDocNumber || !$iid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required parameters: doc_number, grn_number, and iid are required.'
                ], 400);
            }

            // Fetch all products from the selected Purchase Order
            $poProducts = TransactionDetail::where('doc_no', $poDocNumber)
                ->orderBy('line_no')
                ->get();

            // Create new TempTransactionDetail records for the GRN
            foreach ($poProducts as $poProduct) {
                TempTransactionDetail::create([
                    'temp_transaction_header_id' => 0,
                    'doc_no' => $grnDocNumber,
                    'iid' => $iid,
                    'line_no' => $poProduct->line_no,
                    'prod_code' => $poProduct->prod_code,
                    'prod_name' => $poProduct->prod_name,
                    'purchase_price' => $poProduct->purchase_price,
                    'selling_price' => $poProduct->selling_price,
                    'pack_size' => $poProduct->pack_size,
                    'pack_qty' => $poProduct->pack_qty,
                    'unit_qty' => $poProduct->unit_qty,
                    'free_qty' => $poProduct->free_qty,
                    'total_qty' => $poProduct->total_qty,
                    'amount' => $poProduct->amount,
                    'line_wise_discount_value' => $poProduct->line_wise_discount_value,
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Products from PO have been successfully added to the GRN.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process products: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function loadAllGoodReceiveNotes(Request $request)
    {
        if ($request->status == 'drafted') {
            $goodReceiveNote = TempTransactionHeader::where('iid', $request->iid)
                ->with('supplier')
                ->orderBy('id', 'desc')
                ->paginate(10);

            $formattedData = $goodReceiveNote->getCollection()->map(function ($grn) {
                $data = $grn->toArray();
                $data['supplier_name'] = $grn->supplier ? $grn->supplier->sup_name : null;
                return $data;
            });

            $goodReceiveNote->setCollection($formattedData);

            return response()->json([
                'success' => true,
                'message' => 'Draft good receive note loaded successfully!',
                'status' => 'drafted',
                'data' => $goodReceiveNote->items()
            ]);
        } else {
            $goodReceiveNote = TransactionHeader::where('iid', $request->iid)
                ->with('supplier')
                ->orderBy('id', 'desc')
                ->paginate(10);

            $formattedData = $goodReceiveNote->getCollection()->map(function ($grn) {
                $data = $grn->toArray();
                $data['supplier_name'] = $grn->supplier ? $grn->supplier->sup_name : null;
                return $data;
            });

            $goodReceiveNote->setCollection($formattedData);

            return response()->json([
                'success' => true,
                'message' => 'Applied good receive note loaded successfully!',
                'status' => 'applied',
                'data' => $goodReceiveNote->items()
            ]);
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
                $transactionHeader = TransactionHeader::create([
                    ...$headerData,
                    'doc_no'      => $grnNumber,
                    'temp_doc_no' => $data['doc_no'],
                ]);

                // Load temp products for this temp doc
                $tempProducts = TempTransactionDetail::where('doc_no', $data['doc_no'])
                    ->orderBy('line_no')
                    ->get();

                $transactionDetails = [];
                foreach ($tempProducts as $temp) {
                    $tempData = $temp->toArray();
                    unset($tempData['temp_transaction_header_id'], $tempData['id']);
                    $transactionDetail = TransactionDetail::create([
                        ...$tempData,
                        'transaction_header_id' => $transactionHeader->id,
                        'doc_no'                => $grnNumber,
                    ]);
                    $transactionDetails[] = $transactionDetail;
                }

                // Create StockMaster records for each product
                foreach ($transactionDetails as $detail) {
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
