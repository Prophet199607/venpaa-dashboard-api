<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Product;
use App\Models\Location;
use App\Models\DocNumber;
use Illuminate\Http\Request;
use App\Models\TransactionDetail;
use App\Models\TransactionHeader;
use Illuminate\Support\Facades\DB;
use App\Models\ItemReqTransDetail;
use App\Models\ItemReqTransHeader;
use App\Http\Controllers\Controller;
use App\Models\TempTransactionDetail;
use App\Models\TempTransactionHeader;
use App\Http\Requests\Transaction\ItemReqTransDetailRequest;
use App\Http\Requests\Transaction\ItemReqTransHeaderRequest;
use App\Http\Resources\Transaction\ItemReqTransDetailResource;
use App\Http\Requests\Transaction\TempTransactionHeaderRequest;

class ItemRequestController extends Controller
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
            'product_count' => TempTransactionDetail::where('doc_no', $docNo)
                ->where('temp_transaction_header_id', 0)
                ->count(),
            'created_at' => $firstProduct ? $firstProduct->created_at : null,
        ];
    }

    private function createDetailVersion($originalDetail, $newData = [], $status = 'updated')
    {
        // Create a copy of the original detail with new status
        $versionData = $originalDetail->toArray();
        unset($versionData['id'], $versionData['created_at'], $versionData['updated_at']);

        $versionData = array_merge($versionData, $newData, [
            'status' => $status,
            'is_current' => false,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return ItemReqTransDetail::create($versionData);
    }

    private function getCurrentDetails($doc_no)
    {
        return ItemReqTransDetail::where('doc_no', $doc_no)
            ->where('status', 'active')
            ->where('is_current', true)
            ->orderBy('line_no')
            ->get();
    }

    private function getDetailHistory($doc_no)
    {
        return ItemReqTransDetail::where('doc_no', $doc_no)
            ->orderBy('line_no')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function store(TempTransactionHeaderRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $this->processDiscountAndTax($request->validated());
                $irNumber = DocNumber::generate('IR', 'IR', 8, $data['location']);

                $headerData = $data;
                unset($headerData['id']);
                $itemReqTransHeader = ItemReqTransHeader::create(array_merge($headerData, [
                    'doc_no'      => $irNumber,
                    'temp_doc_no' => $data['doc_no'],
                    'created_by'  => auth()->id(),
                ]));

                // Load temp products for this temp doc
                $tempProducts = TempTransactionDetail::where('doc_no', $data['doc_no'])
                    ->orderBy('line_no')
                    ->get();

                foreach ($tempProducts as $temp) {
                    $tempData = $temp->toArray();
                    unset($tempData['temp_transaction_header_id'], $tempData['id']);
                    ItemReqTransDetail::create(array_merge($tempData, [
                        'item_transaction_header_id'    => $itemReqTransHeader->id,
                        'doc_no'       => $irNumber,
                        'created_by'   => auth()->id(),
                    ]));
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
                    'message' => 'Item request stored successfully.',
                    'data'    => $itemReqTransHeader->fresh(),
                ]);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to store item request.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function loadAllItemRequests(Request $request)
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

        if ($request->status == 'drafted') {
            $itemRequests = TempTransactionHeader::where('iid', $request->iid)
                ->where('location', $userLocation) // Added lately 2026/02/14
                ->whereDate('document_date', '>=', $startDate)
                ->whereDate('document_date', '<=', $endDate)
                ->with('supplier')
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            $formattedData = collect($itemRequests->items())->map(function ($ir) {
                $data = $ir->toArray();
                $data['supplier_name'] = $ir->supplier ? $ir->supplier->sup_name : null;
                return $data;
            });

            return response()->json([
                'success' => true,
                'message' => 'Draft item requests loaded successfully!',
                'status' => 'drafted',
                'data' => $formattedData,
            ]);
        } else {
            $itemRequests = ItemReqTransHeader::where('iid', $request->iid)
                ->where('location', $userLocation) // Added lately 2026/02/14
                ->whereDate('document_date', '>=', $startDate)
                ->whereDate('document_date', '<=', $endDate)
                ->with('supplier')
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            $formattedData = collect($itemRequests->items())->map(function ($ir) {
                $data = $ir->toArray();
                $data['supplier_name'] = $ir->supplier ? $ir->supplier->sup_name : null;
                return $data;
            });

            return response()->json([
                'success' => true,
                'message' => 'Applied item requests loaded successfully!',
                'status' => 'applied',
                'data' => $formattedData,
            ]);
        }
    }

    public function loadItemRequestByCode($doc_number, $status, $iid)
    {
        if ($status == 'applied') {
            $itemReqTransHeaders = ItemReqTransHeader::with([
                'supplier',
                'location',
                'deliveryLocation',
                'itemReqTransDetails' => function ($query) {
                    $query->where('status', 'active')
                        ->where('is_current', true)
                        ->orderBy('line_no');
                },
                'itemReqTransDetails.product.unit'
            ])
                ->where(['doc_no' => $doc_number, 'iid' => $iid])
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Item request loaded successfully!',
                'status' => 'applied',
                'data' => $itemReqTransHeaders
            ]);
        } elseif ($status == 'drafted') {
            $tempTransactionHeaders = TempTransactionHeader::with([
                'supplier',
                'location',
                'deliveryLocation',
                'tempTransactionDetails' => function ($query) {
                    $query->orderBy('line_no');
                },
                'tempTransactionDetails.product.unit'
            ])
                ->where(['doc_no' => $doc_number, 'iid' => $iid])
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Item request loaded successfully!',
                'status' => 'drafted',
                'data' => $tempTransactionHeaders
            ]);
        }
    }

    public function loadAppliedItemRequestsByStatus(Request $request)
    {
        try {
            $status = $request->get('approval_status', 'all');
            $docNo = $request->get('doc_no');
            $perPage = $request->input('per_page', 10);

            $query = ItemReqTransHeader::where('iid', 'IR')
                ->orderBy('id', 'desc');

            if ($status !== 'all') {
                $query->where('approval_status', $status);
            }

            if ($docNo) {
                $query->where('doc_no', 'like', '%' . $docNo . '%');
            }

            $itemRequests = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Applied item requests loaded successfully!',
                'data' => $itemRequests->items(),
                'total' => $itemRequests->total(),
                'current_page' => $itemRequests->currentPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load applied item requests.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUnsavedSessions()
    {
        try {
            $unsavedSessions = TempTransactionDetail::where('created_by', auth()->id())
                ->where('iid', 'IR')
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

    public function updateItemReqProduct(ItemReqTransDetailRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $data = $this->processLineWiseDiscount($data);
            $originalProduct = ItemReqTransDetail::find($id);

            if (!$originalProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            if ($originalProduct->status === 'active' && $originalProduct->is_current) {
                $this->createDetailVersion($originalProduct, [], 'previous');

                $originalProduct->update([
                    'purchase_price' => $data['purchase_price'],
                    'selling_price' => $data['selling_price'],
                    'pack_size' => $data['pack_size'],
                    'pack_qty' => $data['pack_qty'],
                    'unit_qty' => $data['unit_qty'],
                    'free_qty' => $data['free_qty'],
                    'total_qty' => $data['total_qty'],
                    'line_wise_discount_value' => $data['line_wise_discount_value'],
                    'amount' => $data['amount'],
                    'status' => 'active',
                    'is_current' => true,
                    'updated_by' => auth()->id(),
                ]);
            } else {
                $newProduct = $this->createDetailVersion($originalProduct, [
                    'purchase_price' => $data['purchase_price'],
                    'selling_price' => $data['selling_price'],
                    'pack_size' => $data['pack_size'],
                    'pack_qty' => $data['pack_qty'],
                    'unit_qty' => $data['unit_qty'],
                    'free_qty' => $data['free_qty'],
                    'total_qty' => $data['total_qty'],
                    'line_wise_discount_value' => $data['line_wise_discount_value'],
                    'amount' => $data['amount'],
                    'status' => 'active',
                    'is_current' => true,
                ]);
            }

            $response_detail = $this->getCurrentDetails($originalProduct->doc_no);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully!',
                'data' => ItemReqTransDetailResource::collection($response_detail),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteItemReqDetail($doc_no, $line_no)
    {
        try {
            ItemReqTransDetail::where(['doc_no' => $doc_no, 'line_no' => $line_no])->delete();
            $rowsToUpdate = ItemReqTransDetail::where('doc_no', $doc_no)
                ->where('line_no', '>', $line_no)
                ->orderBy('line_no')
                ->get();

            foreach ($rowsToUpdate as $row) {
                $row->line_no = $row->line_no - 1;
                $row->save();
            }

            $response_detail = ItemReqTransDetail::where('doc_no', $doc_no)->orderBy('line_no')->get();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully!',
                'data' => ItemReqTransDetailResource::collection($response_detail),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancelItemReqUpdates($doc_no)
    {
        DB::beginTransaction();

        try {
            // Get all updated records for this document
            $updatedRecords = ItemReqTransDetail::where('doc_no', $doc_no)
                ->where('status', 'active')
                ->where('is_current', true)
                ->where('updated_by', auth()->id())
                ->where('updated_at', '>=', now()->subHours(72))
                ->get();

            foreach ($updatedRecords as $record) {
                // Find the previous version
                $previousVersion = ItemReqTransDetail::where('status', 'previous')
                    ->first();

                if ($previousVersion) {
                    // Reactivate the previous version
                    $previousVersion->update([
                        'status' => 'active',
                        'is_current' => true,
                        'updated_by' => auth()->id(),
                    ]);

                    // Archive the current update
                    $record->update([
                        'status' => 'cancelled',
                        'is_current' => false,
                        'updated_by' => auth()->id(),
                    ]);
                }
            }

            $itemRequest = ItemReqTransHeader::with([
                'supplier',
                'location',
                'deliveryLocation',
                'itemReqTransDetails' => fn($q) => $q->where('is_current', true)->orderBy('line_no'),
                'itemReqTransDetails.product.unit'
            ])->where('doc_no', $doc_no)->first();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Updates cancelled successfully!',
                'data' => $itemRequest,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel updates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getItemReqHistory($doc_no)
    {
        try {
            $history = $this->getDetailHistory($doc_no);

            return response()->json([
                'success' => true,
                'data' => ItemReqTransDetailResource::collection($history),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function rejectIr(ItemReqTransHeaderRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();

            $itemRequest = ItemReqTransHeader::where('doc_no', $data['doc_no'])->first();

            if (!$itemRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item request not found.'
                ], 404);
            }

            $itemRequest->update([
                'approval_status' => 'rejected',
                'approval_remarks' => $data['approval_remarks'],
                'is_approved' => false,
                'updated_by' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item request rejected successfully.',
                'data' => $itemRequest
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject item request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeItemReq(ItemReqTransHeaderRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();
            $itemRequest = ItemReqTransHeader::where('doc_no', $data['doc_no'])->first();

            if (!$itemRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item request not found.'
                ], 404);
            }

            $itemRequest->update([
                'subtotal' => $data['subtotal'] ?? $itemRequest->subtotal,
                'net_total' => $data['net_total'] ?? $itemRequest->net_total,
                'discount' => $data['discount'] ?? $itemRequest->discount,
                'dis_per' => $data['dis_per'] ?? $itemRequest->dis_per,
                'tax' => $data['tax'] ?? $itemRequest->tax,
                'tax_per' => $data['tax_per'] ?? $itemRequest->tax_per,
                'approval_status' => 'approved',
                'approval_remarks' => $data['approval_remarks'],
                'is_approved' => true,
                'approved_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $poNumber = DocNumber::generate('PO', 'PO', 8, $data['location']);
            $transactionHeader = TransactionHeader::create([
                'doc_no' => $poNumber,
                'temp_doc_no' => $itemRequest->doc_no,
                'location' => $data['location'],
                'document_date' => $data['document_date'] ?? now(),
                'expected_date' => $data['expected_date'] ?? null,
                'iid' => 'PO',
                'supplier_code' => $data['supplier_code'],
                'delivery_address' => $data['delivery_address'],
                'delivery_location' => $data['delivery_location'],
                'remarks_ref' => $data['remarks_ref'],
                'payment_mode' => 'credit',
                'subtotal' => $data['subtotal'] ?? 0,
                'net_total' => $data['net_total'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'dis_per' => $data['dis_per'] ?? 0,
                'tax' => $data['tax'] ?? 0,
                'tax_per' => $data['tax_per'] ?? 0,
                'created_by' => auth()->id(),
            ]);

            $itemRequestDetails = ItemReqTransDetail::where('doc_no', $data['doc_no'])
                ->where('status', 'active')
                ->where('is_current', 1)
                ->get();
            foreach ($itemRequestDetails as $detail) {
                TransactionDetail::create([
                    'transaction_header_id' => $transactionHeader->id,
                    'doc_no' => $poNumber,
                    'prod_code' => $detail->prod_code,
                    'line_no' => $detail->line_no,
                    'iid' => 'PO',
                    'prod_name' => $detail->prod_name,
                    'purchase_price' => $detail->purchase_price,
                    'selling_price' => $detail->selling_price,
                    'pack_size' => $detail->pack_size,
                    'pack_qty' => $detail->pack_qty,
                    'unit_qty' => $detail->unit_qty,
                    'free_qty' => $detail->free_qty,
                    'total_qty' => $detail->total_qty,
                    'line_wise_discount_value' => $detail->line_wise_discount_value,
                    'amount' => $detail->amount,
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item request approved and PO created successfully.',
                'data' => [
                    'item_request' => $itemRequest,
                    'purchase_order' => $transactionHeader,
                    'po_number' => $poNumber
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve item request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
