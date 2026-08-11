<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Exports\CodManagementExport;
use App\Models\CodManagement;
use App\Models\PaymentSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class CodManagementController extends Controller
{
    public function index(Request $request)
    {
        $userLocation = $request->user()->location ?? null;
        $crmDb = env('DB_CRM_DATABASE');

        $queryResult = CodManagement::when($userLocation, fn($q, $loc) => $q->where('location', $loc))
            ->when($request->filled('start_date'), fn($q) => $q->whereDate('transaction_date', '>=', $request->start_date))
            ->when($request->filled('end_date'), fn($q) => $q->whereDate('transaction_date', '<=', $request->end_date))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status), fn($q) => $q->where(function ($sub) {
                $sub->where('status', 'Pending')->orWhereNull('status');
            }))
            ->orderBy('transaction_date', 'desc')
            ->get();

        // Extract customer codes for POS/CRM lookup
        $customerCodes = $queryResult->pluck('customer')
            ->filter(function ($code) {
                return !empty($code) && $code !== 'DEFAULT' && $code !== 'N/A';
            })
            ->unique()
            ->values()
            ->toArray();

        $crmCustomers = [];
        if (!empty($customerCodes)) {
            try {
                $placeholders = implode(',', array_fill(0, count($customerCodes), '?'));
                $rows = DB::select(
                    "SELECT Cus_Code, Cus_Name FROM `{$crmDb}`.crm_customer WHERE Cus_Code IN ({$placeholders})",
                    $customerCodes
                );
                foreach ($rows as $row) {
                    if (!empty($row->Cus_Code) && !empty($row->Cus_Name)) {
                        $crmCustomers[$row->Cus_Code] = $row->Cus_Name;
                    }
                }
            } catch (\Exception $e) {
                try {
                    $rows = DB::table('crm_customer')
                        ->whereIn('Cus_Code', $customerCodes)
                        ->select('Cus_Code', 'Cus_Name')
                        ->get();
                    foreach ($rows as $row) {
                        if (!empty($row->Cus_Code) && !empty($row->Cus_Name)) {
                            $crmCustomers[$row->Cus_Code] = $row->Cus_Name;
                        }
                    }
                } catch (\Exception $ex) {
                    $crmCustomers = [];
                }
            }
        }

        $data = $queryResult->map(function ($item) use ($crmCustomers) {
            $docNo = $item->doc_no ?? '';
            $rawCustomer = $item->customer ?? '';

            // Resolve customer name if code exists in CRM map, else fallback to item->customer
            $customerDisplay = $crmCustomers[$rawCustomer] ?? ($item->customer ?? 'N/A');

            return [
                'id' => $item->id,
                'location' => $item->location,
                'transaction_date' => $item->transaction_date,
                'transaction_amount' => $item->transaction_amount,
                'doc_no' => $docNo,
                'receipt_no' => $item->receipt_no ?? '',
                'report_id' => $item->report_id,
                'customer' => $customerDisplay,
                'customer_code' => $rawCustomer,
                'type' => $item->type ?? 'N/A',
                'status' => $item->status ?? 'Pending',
                'received_amount' => $item->received_amount,
                'refund_amount' => $item->refund_amount,
                'courier_charges' => $item->courier_charges,
            ];
        });

        return response()->json($data);
    }

    public function markAsReceived(Request $request, $id)
    {
        $request->validate([
            'received_amount' => 'required|numeric|min:0',
        ]);

        $receivedAmount = (float) $request->input('received_amount');
        $orderNo = $request->input('orderNo');

        $cod = DB::transaction(function () use ($id, $receivedAmount, $orderNo) {
            $cod = CodManagement::findOrFail($id);

            $cod->status = 'Received';
            $cod->received_amount = $receivedAmount;
            $cod->save();

            $codDate = \Carbon\Carbon::parse($cod->transaction_date)->format('d/m/Y');

            PaymentSummary::whereIn('iid', ['COD', 'CODO'])
                ->where('ref_doc_no', $orderNo)
                ->where('transaction_date', $codDate)
                ->get()
                ->each(function ($ps) use ($receivedAmount) {
                    $ps->balance_amount = $ps->transaction_amount - $receivedAmount;
                    $ps->save();
                });

            return $cod;
        });

        return response()->json($cod);
    }

    public function markAsReturned(Request $request, $id)
    {
        $orderNo = $request->input('orderNo');

        $cod = DB::transaction(function () use ($id, $orderNo) {
            $cod = CodManagement::findOrFail($id);

            $cod->status = 'Returned';
            $cod->save();

            $codDate = \Carbon\Carbon::parse($cod->transaction_date)->format('d/m/Y');

            PaymentSummary::whereIn('iid', ['COD', 'CODO'])
                ->where('ref_doc_no', $orderNo)
                ->where('transaction_date', $codDate)
                ->get()
                ->each(function ($ps) {
                    $ps->balance_amount = $ps->transaction_amount;
                    $ps->save();
                });

            return $cod;
        });

        return response()->json($cod);
    }

    public function report(Request $request)
    {
        $query = CodManagement::orderBy('cod_management.transaction_date', 'desc');

        if ($request->filled('location')) {
            $query->where('cod_management.location', $request->location);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('cod_management.transaction_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('cod_management.transaction_date', '<=', $request->end_date);
        }

        if ($request->filled('status')) {
            $query->where('cod_management.status', $request->status);
        }

        $data = $query->leftJoin('locations', 'cod_management.location', '=', 'locations.loca_code')
            ->select('cod_management.*', 'locations.loca_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function details($id)
    {
        $record = CodManagement::findOrFail($id);
        $orderNo = $record->doc_no;
        $receiptNo = $record->receipt_no;

        $cartDb = 'venpaa-cart';

        // 1. Try web checkout (delivery orders)
        if ($orderNo) {
            $checkout = DB::selectOne("
                SELECT co.*, u.fname, u.lname, u.email, u.phone,
                       u.address, u.city, u.province, u.postal_code, u.country,
                       u.platform
                FROM `{$cartDb}`.checkouts co
                JOIN `{$cartDb}`.users u ON co.user_id = u.id
                WHERE co.order_id = ?
                LIMIT 1
            ", [$orderNo]);

            if ($checkout) {
                $payload = json_decode($checkout->payload, true);
                $items = $payload['items'] ?? $payload['payload_items'] ?? [];
                $totals = $payload['totals'] ?? [];

                $isCod = ((int) $checkout->type === 1);
                $platform = $checkout->platform ?? '';
                $iid = in_array($platform, ['3', 'WEB', 'website', 'web'], true) ? 'WEB' : 'APP';
                $paymentMethod = $isCod ? 'COD' : ($checkout->type == 2 ? 'Card payment' : 'Mintpay');

                $mappedItems = array_map(function ($item) {
                    $price = (float) ($item['product']['selling_price'] ?? $item['price'] ?? 0);
                    $qty = (float) ($item['quantity'] ?? 1);
                    return [
                        'prod_code' => $item['product']['prod_code'] ?? '',
                        'prod_name' => $item['product']['prod_name'] ?? $item['prod_name'] ?? 'Unknown',
                        'qty' => $qty,
                        'price' => $price,
                        'total' => $price * $qty,
                    ];
                }, $items);

                return response()->json([
                    'source' => 'WEB',
                    'order_type' => 'CHECKOUT',
                    'order_no' => $orderNo,
                    'customer_name' => trim(($checkout->fname ?? '') . ' ' . ($checkout->lname ?? '')),
                    'customer_email' => $checkout->email ?? '',
                    'customer_phone' => $checkout->phone ?? '',
                    'customer_address' => $checkout->address ?? '',
                    'customer_city' => $checkout->city ?? '',
                    'customer_province' => $checkout->province ?? '',
                    'customer_postal_code' => $checkout->postal_code ?? '',
                    'customer_country' => $checkout->country ?? '',
                    'transaction_date' => $checkout->created_at ?? '',
                    'payment_method' => $paymentMethod,
                    'iid' => $iid,
                    'items' => $mappedItems,
                    'totals' => [
                        'original_sub_total' => (float) ($totals['originalSubTotal'] ?? 0),
                        'product_discount' => (float) ($totals['productDiscountTotal'] ?? 0),
                        'sub_total' => (float) ($totals['subTotal'] ?? 0),
                        'coupon_discount' => (float) ($totals['discountAmount'] ?? 0),
                        'courier_charge' => (float) ($totals['courierCharge'] ?? 0),
                        'cod_charge' => $isCod ? (float) ($totals['codCharge'] ?? 0) : 0,
                        'net_total' => $isCod
                            ? (float) ($totals['netTotalWithCod'] ?? 0)
                            : (float) ($totals['netTotalWithoutCod'] ?? 0),
                    ],
                    'status' => $checkout->status ?? '',
                    'payment_status' => $checkout->payment_status ?? '',
                ]);
            }

            // 2. Try pick_and_collect
            $pc = DB::selectOne("
                SELECT pc.*, u.fname, u.lname, u.email, u.phone
                FROM `{$cartDb}`.pick_and_collects pc
                JOIN `{$cartDb}`.users u ON pc.user_id = u.id
                WHERE pc.pick_and_collect_id = ?
                LIMIT 1
            ", [$orderNo]);

            if ($pc) {
                $isCod = ((int) $pc->type === 1);
                $paymentMethod = $isCod ? 'COD' : ($pc->type == 2 ? 'Card payment' : 'Mintpay');
                $platform = $pc->platform ?? '';
                $iid = in_array($platform, ['3', 'WEB', 'website', 'web'], true) ? 'WEB' : 'APP';

                $payload = json_decode($pc->payload ?? '{}', true);
                $pcItems = $payload['items'] ?? [];

                $mappedItems = array_map(function ($item) {
                    $price = (float) ($item['product']['selling_price'] ?? $item['price'] ?? 0);
                    $qty = (float) ($item['quantity'] ?? $item['qty'] ?? 1);
                    return [
                        'prod_code' => $item['product']['prod_code'] ?? $item['prod_code'] ?? '',
                        'prod_name' => $item['product']['prod_name'] ?? $item['prod_name'] ?? 'Unknown',
                        'qty' => $qty,
                        'price' => $price,
                        'total' => $price * $qty,
                    ];
                }, $pcItems);

                return response()->json([
                    'source' => 'WEB',
                    'order_type' => 'PICK_AND_COLLECT',
                    'order_no' => $orderNo,
                    'customer_name' => trim(($pc->fname ?? '') . ' ' . ($pc->lname ?? '')),
                    'customer_email' => $pc->email ?? '',
                    'customer_phone' => $pc->phone ?? '',
                    'customer_address' => '',
                    'transaction_date' => $pc->created_at ?? '',
                    'payment_method' => $paymentMethod,
                    'iid' => $iid,
                    'location' => $pc->location ?? '',
                    'location_name' => $pc->location_name ?? '',
                    'items' => $mappedItems,
                    'totals' => [
                        'sub_total' => (float) ($pc->net_amount ?? 0) + (float) ($pc->discount_amount ?? 0),
                        'discount' => (float) ($pc->discount_amount ?? 0),
                        'net_total' => (float) ($pc->net_amount ?? 0),
                    ],
                    'status' => $pc->status ?? '',
                    'payment_status' => $pc->payment_status ?? '',
                ]);
            }
        }

        // 3. Try POS data
        if ($receiptNo) {
            $crmDb = env('DB_CRM_DATABASE');

            $posRow = DB::selectOne("
                SELECT 
                    cm.*,

                    pt.Operator,
                    pt.PaymentCategory,
                    pt.PaymentType,
                    pt.subTotal,
                    pt.Discount,
                    pt.NetTotal,
                    pt.payment,
                    pt.Balance,
                    pt.CODCharge,
                    pt.CourierCharge,

                    COALESCE(
                    (
                        SELECT 
                        JSON_ARRAYAGG(
                            JSON_OBJECT(
                            'Item_Code',      COALESCE(dri.Item_Code, ''),
                            'Item_Descrip',   COALESCE(dri.Item_Descrip, ''),
                            'Unit_Price',     COALESCE(dri.Unit_Price, 0),
                            'SalesQty',       COALESCE(dri.SalesQty, 0),
                            'SalesAmount',    COALESCE(dri.SalesAmount, 0),
                            'DiscountAmount', COALESCE(dri.SalesAmount, 0) - COALESCE(dri.Unit_Price, 0) * COALESCE(dri.SalesQty, 0),
                            'ReturnQty',      COALESCE(dri.ReturnQty, 0),
                            'ReturnAmount',   COALESCE(dri.ReturnAmount, 0)
                            )
                        )
                        FROM DayEnd_ReceiptItem dri
                        WHERE dri.Receipt_No = cm.receipt_no
                        AND dri.BillDate = CAST(cm.transaction_date AS DATE)
                        AND dri.Loca = SUBSTRING(cm.`location`, 2)
                        AND dri.Item_Code <> 'DISCOUNT'
                    ), 
                    JSON_ARRAY()
                    ) AS items

                FROM cod_management cm

                LEFT JOIN (
                    SELECT 
                    Receipt_No,
                    Loca,
                    TransactionDate,
                    MAX(UserName) AS Operator,
                    COALESCE(CAST(
                        SUBSTRING_INDEX(
                        GROUP_CONCAT(CASE WHEN Iid = 'SBTT' THEN Amount END ORDER BY Id_No ASC),
                        ',', 1
                        ) AS DECIMAL(10,2)
                    ), 0) AS subTotal,
                    SUM(CASE WHEN Iid = '005' THEN Amount ELSE 0 END) AS Discount,
                    SUM(CASE WHEN Iid IN ('CSHS', 'CRDS') THEN Amount ELSE 0 END) AS NetTotal,
                    SUM(CASE WHEN Iid IN ('CRD', 'CSH') THEN Amount ELSE 0 END) AS payment,
                    SUM(CASE WHEN Iid = 'BAL' AND Item_Descrip = 'BALANCE' THEN Amount ELSE 0 END) AS Balance,
                    SUM(CASE WHEN Iid = 'CODC' THEN Amount ELSE 0 END) AS CODCharge,
                    SUM(CASE WHEN Iid = 'CODCC' THEN Amount ELSE 0 END) AS CourierCharge,
                    MAX(CASE
                        WHEN Iid = 'CSHS' THEN 'CASH'
                        WHEN Iid = 'CRDS' THEN 'CREDIT'
                    END) AS PaymentCategory,
                    MAX(CASE WHEN Iid IN ('CSHS', 'CRDS') THEN Item_Descrip END) AS PaymentType
                    FROM DayEnd_Pos_Transaction
                    GROUP BY Loca, Receipt_No, TransactionDate
                ) pt ON pt.Receipt_No = cm.receipt_no
                    AND pt.Loca = SUBSTRING(cm.`location`, 2)
                    AND pt.TransactionDate >= CAST(cm.transaction_date AS DATE) 
                    AND pt.TransactionDate < CAST(DATE_ADD(cm.transaction_date, INTERVAL 1 DAY) AS DATE)

                WHERE cm.id = ?
            ", [$id]);

            if ($posRow && $posRow->NetTotal !== null) {
                // Items are already aggregated as JSON by the correlated DayEnd_ReceiptItem subquery
                $rawItems    = json_decode($posRow->items ?? '[]', true) ?: [];
                $mappedItems = array_map(fn($item) => [
                    'prod_code'     => $item['Item_Code']      ?? '',
                    'prod_name'     => $item['Item_Descrip']   ?? 'Unknown',
                    'qty'           => (float) ($item['SalesQty']       ?? 0),
                    'price'         => (float) ($item['Unit_Price']     ?? 0),
                    'discount'      => (float) ($item['DiscountAmount'] ?? 0),
                    'total'         => (float) ($item['SalesAmount']    ?? 0),
                    'return_qty'    => (float) ($item['ReturnQty']      ?? 0),
                    'return_amount' => (float) ($item['ReturnAmount']   ?? 0),
                ], $rawItems);
                $totalItemDiscount = array_sum(array_column($mappedItems, 'discount'));

                // Resolve customer name from CRM
                $customerCode  = trim($record->customer ?? '');
                $customerName  = '';
                $customerPhone = '';

                if ($customerCode && strtoupper($customerCode) !== 'DEFAULT') {
                    try {
                        $crmRow = DB::selectOne(
                            "SELECT Cus_Name, Mobile FROM `{$crmDb}`.crm_customer WHERE Cus_Code = ? LIMIT 1",
                            [$customerCode]
                        );
                        $customerName  = $crmRow->Cus_Name ?? '';
                        $customerPhone = $crmRow->Mobile   ?? '';
                    } catch (\Exception $e) {
                        try {
                            $crmRow = DB::table('crm_customer')
                                ->where('Cus_Code', $customerCode)
                                ->select('Cus_Name', 'Mobile')
                                ->first();
                            $customerName  = $crmRow->Cus_Name ?? '';
                            $customerPhone = $crmRow->Mobile   ?? '';
                        } catch (\Exception $ex) {
                            // leave empty
                        }
                    }
                }

                $paymentCategory = $posRow->PaymentCategory ?? '';
                $paymentType     = $posRow->PaymentType     ?? '';
                $courierCharge   = (float) ($posRow->CourierCharge ?? 0);
                $codCharge       = (float) ($posRow->CODCharge     ?? 0);

                if ($paymentCategory === 'CASH') {
                    $paymentMethod = 'Cash';
                } elseif ($paymentType) {
                    $paymentMethod = $paymentType;
                } elseif ($courierCharge > 0) {
                    $paymentMethod = 'Speed Post (Bank Transfer)';
                } else {
                    $paymentMethod = 'COD';
                }

                $isDefault = strtoupper($customerCode) === 'DEFAULT' || $customerCode === '';

                return response()->json([
                    'source'           => 'POS',
                    'order_type'       => 'DAYEND',
                    'order_no'         => $orderNo,
                    'receipt_no'       => $receiptNo,
                    'customer_code'    => $isDefault ? '' : $customerCode,
                    'customer_name'    => $customerName ?: ($isDefault ? '' : $customerCode),
                    'customer_phone'   => $customerPhone,
                    'customer_address' => '',
                    'operator'         => $posRow->Operator         ?? '',
                    'transaction_date' => $record->transaction_date ?? '',
                    'payment_method'   => $paymentMethod,
                    'payment_category' => $paymentCategory,
                    'iid'              => 'POS',
                    'location'         => $record->location         ?? '',
                    'items'            => $mappedItems,
                    'totals'           => [
                        'sub_total'      => (float) ($posRow->subTotal  ?? 0),
                        'discount'       => (float) ($posRow->Discount ?? $totalItemDiscount),
                        'courier_charge' => $courierCharge,
                        'cod_charge'     => $codCharge,
                        'net_total'      => (float) ($record->transaction_amount ?? $posRow->NetTotal ?? 0),
                        'payment'        => (float) ($posRow->payment   ?? 0),
                        'balance'        => (float) ($posRow->Balance   ?? 0),
                    ],
                    'status' => $record->status ?? '',
                ]);
            }
        }

        return response()->json(['error' => 'Order details not found'], 404);
    }

    public function exportReport(Request $request)
    {
        $query = CodManagement::orderBy('cod_management.transaction_date', 'desc');

        if ($request->filled('location')) {
            $query->where('cod_management.location', $request->location);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('cod_management.transaction_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('cod_management.transaction_date', '<=', $request->end_date);
        }

        if ($request->filled('status')) {
            $query->where('cod_management.status', $request->status);
        }

        $data = $query->leftJoin('locations', 'cod_management.location', '=', 'locations.loca_code')
            ->select('cod_management.*', 'locations.loca_name')
            ->get();

        return Excel::download(
            new CodManagementExport($data->toArray()),
            'COD_Management_Report.xlsx',
        );
    }
}
