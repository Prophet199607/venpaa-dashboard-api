<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\TransactionHeader;
use Illuminate\Http\Request;

class AcceptGoodNoteController extends Controller
{
    public function loadAllAgns(Request $request)
    {
        if ($request->status == 'pending') {
            $pendingAgn = TransactionHeader::where('iid', $request->iid)
                ->orderBy('id', 'desc')
                ->paginate(10);

            $formattedData = $pendingAgn->getCollection()->map(function ($agn) {
                $data = $agn->toArray();
                return $data;
            });

            $pendingAgn->setCollection($formattedData);

            return response()->json([
                'success' => true,
                'message' => 'Pending AGN loaded successfully!',
                'status' => 'pending',
                'data' => $pendingAgn->items()
            ]);
        } else {
            $appliedAgn = TransactionHeader::where('iid', $request->iid)
                ->orderBy('id', 'desc')
                ->paginate(10);

            $formattedData = $appliedAgn->getCollection()->map(function ($agn) {
                $data = $agn->toArray();
                return $data;
            });

            $appliedAgn->setCollection($formattedData);

            return response()->json([
                'success' => true,
                'message' => 'Applied AGN loaded successfully!',
                'status' => 'applied',
                'data' => $appliedAgn->items()
            ]);

        }
    }

    public function loadAgnByCode($doc_number, $status, $iid)
    {
        if ($status == 'applied') {
            $transactionHeaders = TransactionHeader::with([
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
                'message' => 'Applied AGN loaded successfully!',
                'status' => 'applied',
                'data' => $transactionHeaders
            ]);
        } elseif ($status == 'pending') {
            $transactionHeaders = TransactionHeader::with([
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
                'message' => 'Pending AGN loaded successfully!',
                'status' => 'drafted',
                'data' => $transactionHeaders
            ]);
        }
    }
}
