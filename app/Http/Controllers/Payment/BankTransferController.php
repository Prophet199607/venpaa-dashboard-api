<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankTransferController extends Controller
{
    /**
     * Display a listing of Bank Transfer orders from DayEnd tables.
     */
    public function index(Request $request)
    {
        $userLocation = $request->user()->location ?? null;
        $crmDb = env('DB_CRM_DATABASE');

        // Base query from DayEnd_Pos_Transaction for Bank Transfer payments
        $query = DB::table('DayEnd_Pos_Transaction as pt')
            ->where('pt.Iid', 'CRDS')
            ->where(function ($q) {
                $q->where('pt.Item_Descrip', 'Bank Transfer')
                  ->orWhere('pt.Item_Descrip', 'Delivery (Bank Transfer)')
                  ->orWhere('pt.Item_Descrip', 'LIKE', '%Bank Transfer%');
            });

        // Filter by user location or requested location
        if ($request->filled('location')) {
            $loc = $request->location;
            $cleanLoc = ltrim($loc, '0');
            $padded = str_pad($cleanLoc, 2, '0', STR_PAD_LEFT);
            $query->where(function ($q) use ($loc, $cleanLoc, $padded) {
                $q->where('pt.Loca', $loc)
                  ->orWhere('pt.Loca', $cleanLoc)
                  ->orWhere('pt.Loca', $padded);
            });
        } elseif ($userLocation) {
            $cleanLoc = ltrim($userLocation, '0');
            $padded = str_pad($cleanLoc, 2, '0', STR_PAD_LEFT);
            $query->where(function ($q) use ($userLocation, $cleanLoc, $padded) {
                $q->where('pt.Loca', $userLocation)
                  ->orWhere('pt.Loca', $cleanLoc)
                  ->orWhere('pt.Loca', $padded);
            });
        }

        // Filter by date range (using TransactionDate or BillDate)
        if ($request->filled('start_date')) {
            $startDate = $request->start_date . ' 00:00:00';
            $query->where('pt.TransactionDate', '>=', $startDate);
        }

        if ($request->filled('end_date')) {
            $endDate = $request->end_date . ' 23:59:59';
            $query->where('pt.TransactionDate', '<=', $endDate);
        }

        // Filter by payment sub-type if selected
        if ($request->filled('type') && $request->type !== 'All') {
            $query->where('pt.Item_Descrip', $request->type);
        }

        // Filter by search keyword
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('pt.Receipt_No', 'LIKE', "%{$search}%")
                  ->orWhere('pt.Item_Code', 'LIKE', "%{$search}%")
                  ->orWhere('pt.Customer', 'LIKE', "%{$search}%")
                  ->orWhere('pt.UserName', 'LIKE', "%{$search}%");
            });
        }

        $results = $query->select(
            'pt.Id_No as id',
            'pt.Loca as location',
            'pt.Receipt_No as receipt_no',
            'pt.BillDate as bill_date',
            'pt.BillTime as bill_time',
            'pt.TransactionDate as transaction_date',
            'pt.Item_Descrip as payment_method',
            'pt.Amount as amount',
            'pt.Item_Code as reference_no',
            'pt.Customer as customer_code',
            'pt.UserName as operator',
            'pt.ReportID as report_id'
        )
        ->orderBy('pt.TransactionDate', 'desc')
        ->get();

        // 1. Fetch location names
        $locationMap = [];
        try {
            $locations = DB::table('locations')->select('loca_code', 'loca_name')->get();
            foreach ($locations as $loc) {
                $codeKey = ltrim($loc->loca_code, '0');
                $locationMap[$codeKey] = $loc->loca_name;
                $locationMap[$loc->loca_code] = $loc->loca_name;
            }
        } catch (\Exception $e) {
            $locationMap = [];
        }

        // 2. Extract customer codes and fetch details from CRM DB
        $customerCodes = $results->pluck('customer_code')
            ->filter(function ($code) {
                return !empty($code) && strtoupper($code) !== 'DEFAULT' && $code !== 'N/A';
            })
            ->unique()
            ->values()
            ->toArray();

        $crmCustomers = [];
        if (!empty($customerCodes) && !empty($crmDb)) {
            try {
                $placeholders = implode(',', array_fill(0, count($customerCodes), '?'));
                $rows = DB::select(
                    "SELECT Cus_Code, Cus_Name, Mobile, E_mail FROM `{$crmDb}`.crm_customer WHERE Cus_Code IN ({$placeholders})",
                    $customerCodes
                );
                foreach ($rows as $row) {
                    if (!empty($row->Cus_Code)) {
                        $crmCustomers[$row->Cus_Code] = [
                            'name' => $row->Cus_Name ?? '',
                            'mobile' => $row->Mobile ?? '',
                            'email' => $row->E_mail ?? '',
                        ];
                    }
                }
            } catch (\Exception $e) {
                $crmCustomers = [];
            }
        }

        // Map final response
        $data = $results->map(function ($item) use ($locationMap, $crmCustomers) {
            $rawCust = trim($item->customer_code ?? '');
            $crmInfo = $crmCustomers[$rawCust] ?? null;

            $isDefaultCustomer = empty($rawCust) || strtoupper($rawCust) === 'DEFAULT';
            $customerName = $crmInfo['name'] ?? ($isDefaultCustomer ? 'DEFAULT' : $rawCust);
            $customerMobile = $crmInfo['mobile'] ?? '';

            $locKey = ltrim($item->location, '0');
            $locationName = $locationMap[$locKey] ?? $locationMap[$item->location] ?? ("Branch " . $item->location);

            $amount = (float) ($item->amount ?? 0);

            return [
                'id' => (string) $item->id,
                'receipt_no' => $item->receipt_no ?? '',
                'order_no' => $item->receipt_no ?? '',
                'location' => $item->location,
                'location_name' => $locationName,
                'transaction_date' => $item->transaction_date ?? $item->bill_date,
                'bill_date' => $item->bill_date ?? '',
                'bill_time' => $item->bill_time ?? '',
                'payment_method' => $item->payment_method ?? 'Bank Transfer',
                'reference_no' => $item->reference_no ?? '',
                'customer_code' => $isDefaultCustomer ? '' : $rawCust,
                'customer_name' => $customerName,
                'customer_mobile' => $customerMobile,
                'operator' => $item->operator ?? '',
                'amount' => $amount,
                'status' => 'Completed',
                'report_id' => $item->report_id ?? null,
            ];
        });

        return response()->json($data);
    }

    /**
     * Display detailed information of a specific Bank Transfer order.
     */
    public function details(Request $request, $id)
    {
        $crmDb = env('DB_CRM_DATABASE');

        // Fetch POS Bank Transfer transaction record
        $record = DB::table('DayEnd_Pos_Transaction')
            ->where('Id_No', $id)
            ->first();

        if (!$record) {
            return response()->json(['error' => 'Bank Transfer order not found.'], 404);
        }

        $receiptNo = $record->Receipt_No;
        $loca = $record->Loca;
        $billDateStr = $record->BillDate; // Format e.g. "12/09/2026"
        $customerCode = trim($record->Customer ?? '');
        $bankRef = $record->Item_Code ?? '';
        $paymentMethod = $record->Item_Descrip ?? 'Bank Transfer';

        // 1. Fetch Ordered Items from DayEnd_ReceiptItem
        $items = [];
        try {
            $rawItems = DB::select("
                SELECT 
                    dri.Item_Code as prod_code,
                    dri.Item_Descrip as prod_name,
                    COALESCE(dri.Unit_Price, 0) as price,
                    COALESCE(dri.SalesQty, 0) as qty,
                    COALESCE(dri.SalesAmount, 0) as total,
                    COALESCE(dri.DiscountAmount, 0) as discount,
                    COALESCE(dri.ReturnQty, 0) as return_qty,
                    COALESCE(dri.ReturnAmount, 0) as return_amount
                FROM DayEnd_ReceiptItem dri
                WHERE dri.Receipt_No = ?
                  AND dri.Loca = ?
                  AND dri.BillDate = STR_TO_DATE(?, '%d/%m/%Y')
                  AND dri.Item_Code <> 'DISCOUNT'
            ", [$receiptNo, $loca, $billDateStr]);

            $items = array_map(function ($row) {
                return [
                    'prod_code' => $row->prod_code ?? '',
                    'prod_name' => $row->prod_name ?? 'Unknown',
                    'price' => (float) ($row->price ?? 0),
                    'qty' => (float) ($row->qty ?? 0),
                    'total' => (float) ($row->total ?? 0),
                    'discount' => (float) ($row->discount ?? 0),
                    'return_qty' => (float) ($row->return_qty ?? 0),
                    'return_amount' => (float) ($row->return_amount ?? 0),
                ];
            }, $rawItems);
        } catch (\Exception $e) {
            $items = [];
        }

        // 2. Fetch POS Summary Breakdown for this receipt
        $totals = [
            'sub_total' => 0,
            'discount' => 0,
            'courier_charge' => 0,
            'cod_charge' => 0,
            'net_total' => (float) ($record->Amount ?? 0),
            'payment' => (float) ($record->Amount ?? 0),
            'balance' => 0,
        ];
        $operator = $record->UserName ?? '';

        try {
            $posSummary = DB::selectOne("
                SELECT 
                    MAX(UserName) AS operator,
                    COALESCE(CAST(
                        SUBSTRING_INDEX(
                            GROUP_CONCAT(CASE WHEN Iid = 'SBTT' THEN Amount END ORDER BY Id_No ASC),
                            ',', 1
                        ) AS DECIMAL(10,2)
                    ), 0) AS sub_total,
                    SUM(CASE WHEN Iid = '005' THEN Amount ELSE 0 END) AS discount,
                    SUM(CASE WHEN Iid IN ('CSHS', 'CRDS') THEN Amount ELSE 0 END) AS net_total,
                    SUM(CASE WHEN Iid IN ('CRD', 'CSH') THEN Amount ELSE 0 END) AS payment,
                    SUM(CASE WHEN Iid = 'BAL' AND Item_Descrip = 'BALANCE' THEN Amount ELSE 0 END) AS balance,
                    SUM(CASE WHEN Iid = 'CODC' THEN Amount ELSE 0 END) AS cod_charge,
                    SUM(CASE WHEN Iid = 'CODCC' THEN Amount ELSE 0 END) AS courier_charge
                FROM DayEnd_Pos_Transaction
                WHERE Loca = ? 
                  AND Receipt_No = ? 
                  AND BillDate = ?
                GROUP BY Loca, Receipt_No, BillDate
            ", [$loca, $receiptNo, $billDateStr]);

            if ($posSummary) {
                $operator = $posSummary->operator ?? $operator;
                $totals = [
                    'sub_total' => (float) ($posSummary->sub_total ?? 0),
                    'discount' => (float) ($posSummary->discount ?? 0),
                    'courier_charge' => (float) ($posSummary->courier_charge ?? 0),
                    'cod_charge' => (float) ($posSummary->cod_charge ?? 0),
                    'net_total' => (float) ($posSummary->net_total ?? $record->Amount ?? 0),
                    'payment' => (float) ($posSummary->payment ?? $record->Amount ?? 0),
                    'balance' => (float) ($posSummary->balance ?? 0),
                ];
            }
        } catch (\Exception $e) {
            // Keep default totals
        }

        // 3. Resolve Customer Profile from $crmDb
        $customer = [
            'code' => $customerCode,
            'name' => 'DEFAULT',
            'mobile' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'city' => '',
            'nic' => '',
        ];

        if (!empty($customerCode) && strtoupper($customerCode) !== 'DEFAULT' && !empty($crmDb)) {
            try {
                $crmRow = DB::selectOne("
                    SELECT 
                        Cus_Code, Cus_Name, Mobile, PhoneNo, E_mail, 
                        Address1, Address2, Address3, City, NICNumber
                    FROM `{$crmDb}`.crm_customer 
                    WHERE Cus_Code = ? 
                    LIMIT 1
                ", [$customerCode]);

                if ($crmRow) {
                    $addressParts = array_filter([
                        $crmRow->Address1 ?? null,
                        $crmRow->Address2 ?? null,
                        $crmRow->Address3 ?? null,
                        $crmRow->City ?? null,
                    ]);

                    $customer = [
                        'code' => $crmRow->Cus_Code ?? $customerCode,
                        'name' => $crmRow->Cus_Name ?? $customerCode,
                        'mobile' => $crmRow->Mobile ?? '',
                        'phone' => $crmRow->PhoneNo ?? '',
                        'email' => $crmRow->E_mail ?? '',
                        'address' => implode(', ', $addressParts),
                        'city' => $crmRow->City ?? '',
                        'nic' => $crmRow->NICNumber ?? '',
                    ];
                } else {
                    $customer['name'] = $customerCode;
                }
            } catch (\Exception $e) {
                $customer['name'] = $customerCode;
            }
        }

        // 4. Resolve Location Name
        $locationName = "Branch " . $loca;
        try {
            $locRow = DB::table('locations')
                ->where('loca_code', $loca)
                ->orWhere('loca_code', str_pad($loca, 3, '0', STR_PAD_LEFT))
                ->first();
            if ($locRow) {
                $locationName = $locRow->loca_name;
            }
        } catch (\Exception $e) {
            // Keep fallback
        }

        return response()->json([
            'id' => (string) $record->Id_No,
            'source' => 'POS',
            'order_no' => $receiptNo,
            'receipt_no' => $receiptNo,
            'transaction_date' => $record->TransactionDate ?? $record->BillDate,
            'bill_date' => $record->BillDate ?? '',
            'bill_time' => $record->BillTime ?? '',
            'operator' => $operator,
            'payment_method' => $paymentMethod,
            'reference_no' => $bankRef,
            'location' => $loca,
            'location_name' => $locationName,
            'status' => 'Completed',
            'customer' => $customer,
            'items' => $items,
            'totals' => $totals,
        ]);
    }
}
