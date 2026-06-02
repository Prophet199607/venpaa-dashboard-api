<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\SalesReportExport;
use App\Exports\CurrentStockExport;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Class ReportController
 * Handles various application reports.
 */
class ReportController extends Controller
{
    /**
     * Get POS sales summary report
     */
    public function getPosSalesSummaryReport(Request $request)
    {
        try {
            $locaRaw = $request->input('location', $request->input('Loca', ''));
            $location = str_pad(ltrim($locaRaw, '0'), 2, '0', STR_PAD_LEFT);

            $dateFrom = $request->input('dateFrom', '');
            $dateTo = $request->input('dateTo', '');

            DB::statement('SET @pErrorCode = 0');
            $summary = DB::select('CALL sp_PosSalesSummaryReport_v2(@pErrorCode, ?, ?, ?)', [
                $location,
                $dateFrom,
                $dateTo
            ]);

            $errorCode = DB::select('SELECT @pErrorCode as error_code')[0]->error_code;

            if ($errorCode != 0) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Stored procedure error',
                    'error_code' => $errorCode
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'POS sales summary report fetched successfully',
                'data' => $summary
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch POS sales summary report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get POS collection summary report (Daily Collection)
     */
    public function getPosCollectionSummaryReport(Request $request)
    {
        try {
            $locaRaw = $request->input('location', $request->input('Loca', ''));
            $location = str_pad(ltrim($locaRaw, '0'), 2, '0', STR_PAD_LEFT);

            $dateFrom = $request->input('dateFrom', '');
            $dateTo = $request->input('dateTo', '');

            DB::statement('SET @pErrorCode = 0');

            $summary = DB::select('CALL sp_PosCollectionSummaryReport(@pErrorCode, ?, ?, ?)', [
                $location,
                $dateFrom,
                $dateTo
            ]);

            $errorCode = DB::select('SELECT @pErrorCode as error_code')[0]->error_code;

            if ($errorCode != 0) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Stored procedure error',
                    'error_code' => $errorCode
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'POS collection summary report fetched successfully',
                'data' => $summary
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch POS collection summary report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Current Stock Report
     */
    public function getCurrentStockReport(Request $request)
    {
        try {
            $location = $request->input('location', $request->input('Loca', ''));
            $supplierCodes = $request->input('supplierCodes', '');
            $department = $request->input('department', '');
            $prodCodes = $request->input('prodCodes', '');
            $category = $request->input('category', '');

            DB::statement('SET @pErrorCode = 0');

            $summary = DB::select('CALL sp_CurrentStockReport(@pErrorCode, ?, ?, ?, ?, ?)', [
                $location,
                $department,
                $category,
                $supplierCodes,
                $prodCodes
            ]);

            $errorCode = DB::select('SELECT @pErrorCode as error_code')[0]->error_code;

            if ($errorCode != 0) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Stored procedure error',
                    'error_code' => $errorCode
                ], 400);
            }

            // Inject Product and Location names
            $uniqueProdCodes = array_values(array_unique(array_column($summary, 'Prod_Code')));
            $products = DB::table('products')->whereIn('prod_code', $uniqueProdCodes)->pluck('prod_name', 'prod_code');

            $uniqueLocas = array_values(array_unique(array_column($summary, 'Loca')));
            $locations = DB::table('locations')->whereIn('loca_code', $uniqueLocas)->pluck('loca_name', 'loca_code');

            foreach ($summary as &$row) {
                $row->Prod_Name = $products[$row->Prod_Code] ?? '';
                $row->Loca_Name = $locations[$row->Loca] ?? '';
            }

            return response()->json([
                'success' => true,
                'message' => 'Current stock report fetched successfully',
                'data' => $summary
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch current stock report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exportCurrentStockReport(Request $request)
    {
        try {
            $location = $request->input('location', $request->input('Loca', ''));
            $supplierCodes = $request->input('supplierCodes', '');
            $department = $request->input('department', '');
            $prodCodes = $request->input('prodCodes', '');
            $category = $request->input('category', '');

            DB::statement('SET @pErrorCode = 0');

            $summary = DB::select('CALL sp_CurrentStockReport(@pErrorCode, ?, ?, ?, ?, ?)', [
                $location,
                $department,
                $category,
                $supplierCodes,
                $prodCodes
            ]);

            $errorCode = DB::select('SELECT @pErrorCode as error_code')[0]->error_code;

            if ($errorCode != 0) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Stored procedure error',
                    'error_code' => $errorCode
                ], 400);
            }

            // Inject Product and Location names
            $uniqueProdCodes = array_values(array_unique(array_column($summary, 'Prod_Code')));
            $products = DB::table('products')->whereIn('prod_code', $uniqueProdCodes)->pluck('prod_name', 'prod_code');

            $uniqueLocas = array_values(array_unique(array_column($summary, 'Loca')));
            $locations = DB::table('locations')->whereIn('loca_code', $uniqueLocas)->pluck('loca_name', 'loca_code');

            foreach ($summary as &$row) {
                $row->Prod_Name = $products[$row->Prod_Code] ?? '';
                $row->Loca_Name = $locations[$row->Loca] ?? '';
            }

            return Excel::download(new CurrentStockExport($summary), 'Current_Stock_Report.xlsx');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export current stock report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSalesReport(Request $request)
    {
        try {
            $locaRaw = $request->input('location', '');
            $location = (trim($locaRaw) === '' || $locaRaw === 'ALL') ? " " : str_pad(ltrim($locaRaw, '0'), 2, '0', STR_PAD_LEFT);
            $dateFrom = $request->input('dateFrom', '');
            $dateTo = $request->input('dateTo', '');
            $viewType = strtoupper($request->input('viewType', 'PRODUCT'));
            $codeFrom = $request->input('codeFrom', '');
            $codeTo = $request->input('codeTo', '');

            // Ensure dates are in dd/mm/yyyy format for SP
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
                $dateFrom = date('d/m/Y', strtotime($dateFrom));
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                $dateTo = date('d/m/Y', strtotime($dateTo));
            }

            $pdo = DB::getPdo();
            $stmt = $pdo->prepare("CALL sp_SalesReport_v3(@pErrorCode, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $location,
                $dateFrom,
                $dateTo,
                $viewType,
                $codeFrom,
                $codeTo
            ]);

            // First Result Set: Report Header
            $header = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Second Result Set: Main Data
            if($stmt->nextRowset()){
                $details = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $details = [];
            }

            // Third Result Set: Totals
            if($stmt->nextRowset()){
                $totals = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $totals = [];
            }

            $stmt->closeCursor();
            
            $errorCodeResult = DB::select('SELECT @pErrorCode as error_code');
            $errorCode = !empty($errorCodeResult) ? $errorCodeResult[0]->error_code : 0;

            if ($errorCode != 0) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Stored procedure error',
                    'error_code' => $errorCode
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sales report fetched successfully',
                'data' => [
                    'header' => !empty($header) ? $header[0] : null,
                    'details' => $details,
                    'totals' => !empty($totals) ? $totals[0] : null
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Web Sales Report from venpaa-cart.checkouts, pick_and_collects,
     * users, and venpaa_new.products.
     */
    public function getWebSalesReport(Request $request)
    {
        try {
            $locaRaw = $request->input('location', '');
            $location = (trim($locaRaw) === '' || $locaRaw === 'ALL') ? '' : str_pad(ltrim($locaRaw, '0'), 3, '0', STR_PAD_LEFT);
            $dateFrom = $request->input('dateFrom', '');
            $dateTo = $request->input('dateTo', '');
            $type = strtoupper($request->input('type', ''));

            $cartDb = 'venpaa-cart';
            $mainDb = env('DB_DATABASE', 'venpaa_new');

            // 1. Build Query for stock_masters as the primary source of online transactions (iid = 'WEB' or 'APP')
            $smWhere = "sm.iid IN ('WEB', 'APP')";
            $smBindings = [];

            if ($type && $type !== 'ALL') {
                $smWhere .= " AND sm.iid = ?";
                $smBindings[] = $type;
            }

            if ($location !== '') {
                $smWhere .= " AND sm.location = ?";
                $smBindings[] = $location;
            }

            if ($dateFrom && $dateTo) {
                $smWhere .= " AND sm.transaction_date >= ? AND sm.transaction_date <= ?";
                $smBindings[] = $dateFrom;
                $smBindings[] = $dateTo;
            } elseif ($dateFrom) {
                $smWhere .= " AND sm.transaction_date >= ?";
                $smBindings[] = $dateFrom;
            } elseif ($dateTo) {
                $smWhere .= " AND sm.transaction_date <= ?";
                $smBindings[] = $dateTo;
            }

            $smSql = "
                SELECT
                    sm.id,
                    sm.transaction_date,
                    sm.doc_no,
                    sm.location,
                    sm.prod_code,
                    sm.iid,
                    ABS(sm.qty) AS qty,
                    sm.selling_price,
                    ABS(sm.amount) AS amount,
                    p.prod_name,
                    sm.created_at
                FROM `{$mainDb}`.stock_masters sm
                LEFT JOIN `{$mainDb}`.products p ON sm.prod_code = p.prod_code
                WHERE {$smWhere}
                ORDER BY sm.created_at DESC
            ";

            $smRows = DB::select($smSql, $smBindings);

            // 2a. Enrich with Location names
            $uniqueLocas = array_values(array_unique(array_map(function ($sm) {
                return $sm->location !== '' ? str_pad(ltrim($sm->location, '0'), 3, '0', STR_PAD_LEFT) : '';
            }, $smRows)));
            $uniqueLocas = array_values(array_filter($uniqueLocas));
            $locationNames = [];
            if (!empty($uniqueLocas)) {
                $locationNames = DB::table('locations')
                    ->whereIn('loca_code', $uniqueLocas)
                    ->pluck('loca_name', 'loca_code')
                    ->toArray();
            }

            // 2. Fetch parallel checkouts (delivery orders) within the date range
            $coBindings = [];
            $coDateClause = '';
            if ($dateFrom && $dateTo) {
                $coDateClause = 'AND DATE(co.created_at) >= ? AND DATE(co.created_at) <= ?';
                $coBindings[] = $dateFrom;
                $coBindings[] = $dateTo;
            } elseif ($dateFrom) {
                $coDateClause = 'AND DATE(co.created_at) >= ?';
                $coBindings[] = $dateFrom;
            } elseif ($dateTo) {
                $coDateClause = 'AND DATE(co.created_at) <= ?';
                $coBindings[] = $dateTo;
            }

            $checkoutSql = "
                SELECT
                    co.id,
                    co.order_id           AS doc_no,
                    co.created_at          AS transaction_date,
                    co.payload,
                    co.status              AS order_status,
                    COALESCE(co.payment_status, 'pending') AS payment_status,
                    u.id                   AS user_id,
                    u.fname,
                    u.lname,
                    u.email,
                    u.phone,
                    u.platform
                FROM `{$cartDb}`.checkouts co
                JOIN `{$cartDb}`.users u ON co.user_id = u.id
                WHERE co.payment_status = 'success'
                {$coDateClause}
                ORDER BY co.created_at DESC
            ";
            $checkoutRows = DB::select($checkoutSql, $coBindings);

            // 3. Fetch parallel pick & collect orders within the date range
            $pcBindings = [];
            $pcDateClause = '';
            if ($dateFrom && $dateTo) {
                $pcDateClause = 'AND DATE(pc.created_at) >= ? AND DATE(pc.created_at) <= ?';
                $pcBindings[] = $dateFrom;
                $pcBindings[] = $dateTo;
            } elseif ($dateFrom) {
                $pcDateClause = 'AND DATE(pc.created_at) >= ?';
                $pcBindings[] = $dateFrom;
            } elseif ($dateTo) {
                $pcDateClause = 'AND DATE(pc.created_at) <= ?';
                $pcBindings[] = $dateTo;
            }

            $pcSql = "
                SELECT
                    pc.id,
                    pc.pick_and_collect_id AS doc_no,
                    pc.created_at           AS transaction_date,
                    pc.prod_code,
                    pc.picked_qty           AS qty,
                    pc.net_amount           AS amount,
                    pc.location,
                    pc.location_name,
                    pc.status               AS order_status,
                    COALESCE(pc.payment_status, 'pending') AS payment_status,
                    u.id                    AS user_id,
                    u.fname,
                    u.lname,
                    u.email,
                    u.phone,
                    u.platform
                FROM `{$cartDb}`.pick_and_collects pc
                JOIN `{$cartDb}`.users u ON pc.user_id = u.id
                WHERE pc.payment_status = 'success'
                {$pcDateClause}
                ORDER BY pc.created_at DESC
            ";
            $pcRows = DB::select($pcSql, $pcBindings);

            // 4. Map checkout items and pick & collect items by prod_code + location + date
            $checkoutItems = [];
            foreach ($checkoutRows as $co) {
                $payload = json_decode($co->payload, true);
                $coLocRaw = $payload['location'] ?? '';
                $locationVal = $coLocRaw !== '' ? str_pad(ltrim($coLocRaw, '0'), 3, '0', STR_PAD_LEFT) : '';
                $platform = $co->platform ?? '';
                $coIid = (in_array($platform, ['3', 'WEB', 'website', 'web'], true)) ? 'WEB' : 'APP';

                $items = $payload['items'] ?? [];
                $customerName = trim(($co->fname ?? '') . ' ' . ($co->lname ?? ''));
                $coDate = date('Y-m-d', strtotime($co->transaction_date));

                foreach ($items as $item) {
                    $product = $item['product'] ?? [];
                    $prodCode = $product['prod_code'] ?? '';
                    $qty = (float) ($item['quantity'] ?? 0);
                    
                    $key = "{$prodCode}_{$locationVal}_{$coDate}";
                    $checkoutItems[$key][] = [
                        'doc_no' => (string) $co->doc_no,
                        'customer_name' => $customerName ?: '-',
                        'email' => $co->email ?? '',
                        'phone' => $co->phone ?? '',
                        'source' => 'CHECKOUT',
                        'order_total' => (float) ($payload['totals']['netTotalWithCod'] ?? $payload['totals']['subTotal'] ?? 0),
                        'item_count' => count($items),
                        'total_qty' => array_sum(array_column($items, 'quantity')),
                        'total_amount' => (float) ($payload['totals']['subTotal'] ?? 0),
                    ];
                }
            }

            $pcItems = [];
            foreach ($pcRows as $pc) {
                $pcLocRaw = $pc->location ?? '';
                $locationVal = $pcLocRaw !== '' ? str_pad(ltrim($pcLocRaw, '0'), 3, '0', STR_PAD_LEFT) : '';
                $platform = $pc->platform ?? '';
                $pcIid = (in_array($platform, ['3', 'WEB', 'website', 'web'], true)) ? 'WEB' : 'APP';

                $customerName = trim(($pc->fname ?? '') . ' ' . ($pc->lname ?? ''));
                $pcDate = date('Y-m-d', strtotime($pc->transaction_date));
                $prodCode = $pc->prod_code ?? '';

                $key = "{$prodCode}_{$locationVal}_{$pcDate}";
                $pcItems[$key][] = [
                    'doc_no' => (string) $pc->doc_no,
                    'customer_name' => $customerName ?: '-',
                    'email' => $pc->email ?? '',
                    'phone' => $pc->phone ?? '',
                    'source' => 'PICK_AND_COLLECT',
                    'order_total' => (float) $pc->amount,
                    'item_count' => 1,
                    'total_qty' => (float) $pc->qty,
                    'total_amount' => (float) $pc->amount,
                ];
            }

            // 5. Build details and orders list from stock_masters primary list and enrich from matches
            $details = [];
            $orderMap = [];

            foreach ($smRows as $sm) {
                $prodCode = $sm->prod_code;
                $smLocRaw = $sm->location ?? '';
                $locationVal = $smLocRaw !== '' ? str_pad(ltrim($smLocRaw, '0'), 3, '0', STR_PAD_LEFT) : '';
                $smDate = date('Y-m-d', strtotime($sm->transaction_date));
                $qty = (float) $sm->qty;
                $price = (float) $sm->selling_price;
                $amount = (float) $sm->amount;
                $iid = $sm->iid;

                // Attempt to match with checkout or pick_and_collect items (with timezone flexibility +/- 1 day)
                $match = null;
                $datesToTry = [
                    $smDate,
                    date('Y-m-d', strtotime($smDate . ' -1 day')),
                    date('Y-m-d', strtotime($smDate . ' +1 day')),
                ];

                foreach ($datesToTry as $tryDate) {
                    $tryKey = "{$prodCode}_{$locationVal}_{$tryDate}";
                    if (!empty($checkoutItems[$tryKey])) {
                        $match = array_shift($checkoutItems[$tryKey]);
                        break;
                    } elseif (!empty($pcItems[$tryKey])) {
                        $match = array_shift($pcItems[$tryKey]);
                        break;
                    }
                }

                if ($match) {
                    $docNo = $match['doc_no'];
                    $customerName = $match['customer_name'];
                    $customerEmail = $match['email'];
                    $phone = $match['phone'];
                    $source = $match['source'];
                    $docKey = ($source === 'CHECKOUT' ? 'CHK-' : 'PC-') . $docNo;

                    if (!isset($orderMap[$docKey])) {
                        $orderMap[$docKey] = [
                            'doc_no'        => $docNo,
                            'date'          => $sm->transaction_date,
                            'customer_name' => $customerName,
                            'email'         => $customerEmail,
                            'phone'         => $phone,
                            'iid'           => $iid,
                            'source'        => $source,
                            'location'      => $locationVal,
                            'location_name' => $locationNames[$locationVal] ?? '',
                            'order_total'   => $match['order_total'],
                            'item_count'    => 0,
                            'total_qty'     => 0,
                            'total_amount'  => 0,
                        ];
                    }
                } else {
                    $docNo = $sm->doc_no ?: ($iid === 'WEB' ? 'WEB_ORDER' : 'APP_ORDER');
                    $customerName = '-';
                    $customerEmail = '';
                    $phone = '';
                    $source = $iid === 'WEB' ? 'CHECKOUT' : 'PICK_AND_COLLECT';
                    $docKey = 'SM-' . $docNo . '-' . $smDate . '-' . $locationVal;

                    if (!isset($orderMap[$docKey])) {
                        $orderMap[$docKey] = [
                            'doc_no'        => $docNo,
                            'date'          => $sm->transaction_date,
                            'customer_name' => $customerName,
                            'email'         => $customerEmail,
                            'phone'         => $phone,
                            'iid'           => $iid,
                            'source'        => $source,
                            'location'      => $locationVal,
                            'location_name' => $locationNames[$locationVal] ?? '',
                            'order_total'   => 0.0,
                            'item_count'    => 0,
                            'total_qty'     => 0,
                            'total_amount'  => 0,
                        ];
                    }
                }

                $details[] = [
                    'doc_no'           => $docNo,
                    'transaction_date' => $sm->transaction_date,
                    'prod_code'        => $prodCode,
                    'prod_name'        => $sm->prod_name ?? '',
                    'qty'              => $qty,
                    'selling_price'    => $price,
                    'amount'           => $amount,
                    'iid'              => $iid,
                    'location'         => $locationVal,
                    'location_name'    => $locationNames[$locationVal] ?? '',
                    'customer_name'    => $customerName,
                    'customer_email'   => $customerEmail,
                    'phone'            => $phone,
                    'source'           => $source,
                ];

                $orderMap[$docKey]['item_count']++;
                $orderMap[$docKey]['total_qty'] += $qty;
                $orderMap[$docKey]['total_amount'] += $amount;
                if (!$match) {
                    $orderMap[$docKey]['order_total'] += $amount;
                }
            }

            // Totals
            $totalQty = 0;
            $totalAmount = 0;
            foreach ($details as $d) {
                $totalQty += $d['qty'];
                $totalAmount += $d['amount'];
            }

            $orders = array_values($orderMap);

            return response()->json([
                'success' => true,
                'message' => 'Web sales report fetched successfully',
                'data' => [
                    'details' => $details,
                    'orders'  => $orders,
                    'totals'  => [
                        'total_qty'    => $totalQty,
                        'total_amount' => $totalAmount,
                        'record_count' => count($details),
                        'order_count'  => count($orders),
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch web sales report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function exportSalesReport(Request $request)
    {
        try {
            $locaRaw = $request->input('location', '');
            $location = (trim($locaRaw) === '' || $locaRaw === 'ALL') ? " " : str_pad(ltrim($locaRaw, '0'), 2, '0', STR_PAD_LEFT);
            $dateFrom = $request->input('dateFrom', '');
            $dateTo = $request->input('dateTo', '');
            $viewType = strtoupper($request->input('viewType', 'PRODUCT'));
            $codeFrom = $request->input('codeFrom', '');
            $codeTo = $request->input('codeTo', '');

            // Ensure dates are in dd/mm/yyyy format for SP
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
                $dateFrom = date('d/m/Y', strtotime($dateFrom));
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                $dateTo = date('d/m/Y', strtotime($dateTo));
            }

            $pdo = DB::getPdo();
            $stmt = $pdo->prepare("CALL sp_SalesReport_v3(@pErrorCode, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $location,
                $dateFrom,
                $dateTo,
                $viewType,
                $codeFrom,
                $codeTo
            ]);

            // First Result Set: Report Header
            $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Second Result Set: Main Data
            if($stmt->nextRowset()){
                $details = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $details = [];
            }

            // Third Result Set: Totals
            if($stmt->nextRowset()){
                $totals = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $totals = [];
            }

            $stmt->closeCursor();
            
            $errorCodeResult = DB::select('SELECT @pErrorCode as error_code');
            $errorCode = !empty($errorCodeResult) ? $errorCodeResult[0]->error_code : 0;

            if ($errorCode != 0) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Stored procedure error',
                    'error_code' => $errorCode
                ], 400);
            }

            // Inject Location names
            $uniqueLocas = array_values(array_unique(array_column($details, 'Loca')));
            $uniqueLocas = array_map(function ($loca) {
                return str_pad($loca, 3, '0', STR_PAD_LEFT);
            }, $uniqueLocas);
            $locations = DB::table('locations')
                ->whereIn('loca_code', $uniqueLocas)
                ->pluck('loca_name', 'loca_code');

            foreach ($details as &$row) {
                $lookupKey = str_pad($row['Loca'], 3, '0', STR_PAD_LEFT);
                $row['Loca_Name'] = $locations[$lookupKey] ?? $row['Loca'];
            }

            return Excel::download(new SalesReportExport($details, !empty($totals) ? $totals[0] : null), 'Sales_Report.xlsx');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export sales report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
