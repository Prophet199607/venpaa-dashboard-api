<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\SalesReportExport;
use App\Exports\CurrentStockExport;
use App\Exports\WebSalesReportExport;
use App\Models\TransactionHeader;
use Carbon\Carbon;
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
            $summary = DB::select('CALL sp_PosSalesSummaryReport(@pErrorCode, ?, ?, ?)', [
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

    public function getSupplierWisePurchasingReport(Request $request)
    {
        try {
            $location = trim((string) $request->input('location', $request->input('Loca', '')));
            $supplierCode = trim((string) $request->input('supplier', $request->input('supplierCode', '')));
            $dateFrom = $this->normalizeReportDate($request->input('dateFrom', $request->input('date_from', '')));
            $dateTo = $this->normalizeReportDate($request->input('dateTo', $request->input('date_to', '')));

            $query = TransactionHeader::query()
                ->where('iid', 'GRN')
                ->with(['supplier', 'transactionDetails']);

            if ($location !== '' && strtoupper($location) !== 'ALL') {
                $query->where('location', $location);
            }

            if ($supplierCode !== '' && strtoupper($supplierCode) !== 'ALL') {
                $query->where('supplier_code', $supplierCode);
            }

            if ($dateFrom) {
                $query->where(function ($subQuery) use ($dateFrom) {
                    $subQuery->whereDate('transaction_date', '>=', $dateFrom)
                        ->orWhere(function ($fallbackQuery) use ($dateFrom) {
                            $fallbackQuery->whereNull('transaction_date')
                                ->whereDate('document_date', '>=', $dateFrom);
                        });
                });
            }

            if ($dateTo) {
                $query->where(function ($subQuery) use ($dateTo) {
                    $subQuery->whereDate('transaction_date', '<=', $dateTo)
                        ->orWhere(function ($fallbackQuery) use ($dateTo) {
                            $fallbackQuery->whereNull('transaction_date')
                                ->whereDate('document_date', '<=', $dateTo);
                        });
                });
            }

            $headers = $query->orderBy('transaction_date', 'desc')
                ->orderBy('document_date', 'desc')
                ->get();

            $uniqueLocas = array_values(array_unique($headers->pluck('location')->filter()->toArray()));
            $locationMap = [];
            if (!empty($uniqueLocas)) {
                $locationRows = DB::table('locations')
                    ->whereIn('loca_code', $uniqueLocas)
                    ->pluck('loca_name', 'loca_code')
                    ->toArray();
                foreach ($uniqueLocas as $code) {
                    $name = $locationRows[$code] ?? '';
                    if (empty($name)) {
                        $padded2 = str_pad(ltrim($code, '0'), 2, '0', STR_PAD_LEFT);
                        $name = $locationRows[$padded2] ?? '';
                    }
                    if (empty($name)) {
                        $padded3 = str_pad(ltrim($code, '0'), 3, '0', STR_PAD_LEFT);
                        $name = $locationRows[$padded3] ?? '';
                    }
                    $locationMap[$code] = $name ?: $code;
                }
            }

            $data = $headers->map(function (TransactionHeader $header) use ($locationMap) {
                $purchaseAmount = (float) $header->transactionDetails()->sum('amount');
                $invoiceValue = (float) ($header->invoice_amount > 0 ? $header->invoice_amount : $header->net_total);

                return [
                    'date' => $header->transaction_date ? $header->transaction_date->format('Y-m-d') : ($header->document_date ? $header->document_date->format('Y-m-d') : ''),
                    'location' => $locationMap[$header->location] ?? '',
                    'supplier' => $header->supplier?->sup_name ?? '',
                    'grn_number' => $header->doc_no,
                    'invoice_number' => $header->invoice_no ?? '',
                    'purchase_type' => strtolower((string) ($header->payment_mode ?? '')),
                    'purchase_amount' => round($purchaseAmount, 2),
                    'vat' => round((float) $header->tax, 2),
                    'invoice_value' => round($invoiceValue, 2),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Supplier wise purchasing report fetched successfully',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch supplier wise purchasing report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function exportSupplierWisePurchasingReport(Request $request)
    {
        try {
            // Reuse the data from getSupplierWisePurchasingReport
            $location = trim((string) $request->input('location', $request->input('Loca', '')));
            $supplierCode = trim((string) $request->input('supplier', $request->input('supplierCode', '')));
            $dateFrom = $this->normalizeReportDate($request->input('dateFrom', $request->input('date_from', '')));
            $dateTo = $this->normalizeReportDate($request->input('dateTo', $request->input('date_to', '')));

            $query = TransactionHeader::query()
                ->where('iid', 'GRN')
                ->with(['supplier', 'transactionDetails']);

            if ($location !== '' && strtoupper($location) !== 'ALL') {
                $query->where('location', $location);
            }

            if ($supplierCode !== '' && strtoupper($supplierCode) !== 'ALL') {
                $query->where('supplier_code', $supplierCode);
            }

            if ($dateFrom) {
                $query->where(function ($subQuery) use ($dateFrom) {
                    $subQuery->whereDate('transaction_date', '>=', $dateFrom)
                        ->orWhere(function ($fallbackQuery) use ($dateFrom) {
                            $fallbackQuery->whereNull('transaction_date')
                                ->whereDate('document_date', '>=', $dateFrom);
                        });
                });
            }

            if ($dateTo) {
                $query->where(function ($subQuery) use ($dateTo) {
                    $subQuery->whereDate('transaction_date', '<=', $dateTo)
                        ->orWhere(function ($fallbackQuery) use ($dateTo) {
                            $fallbackQuery->whereNull('transaction_date')
                                ->whereDate('document_date', '<=', $dateTo);
                        });
                });
            }

            $headers = $query->orderBy('transaction_date', 'desc')
                ->orderBy('document_date', 'desc')
                ->get();

            $uniqueLocas = array_values(array_unique($headers->pluck('location')->filter()->toArray()));
            $locationMap = [];
            if (!empty($uniqueLocas)) {
                $locationRows = DB::table('locations')
                    ->whereIn('loca_code', $uniqueLocas)
                    ->pluck('loca_name', 'loca_code')
                    ->toArray();
                foreach ($uniqueLocas as $code) {
                    $name = $locationRows[$code] ?? '';
                    if (empty($name)) {
                        $padded2 = str_pad(ltrim($code, '0'), 2, '0', STR_PAD_LEFT);
                        $name = $locationRows[$padded2] ?? '';
                    }
                    if (empty($name)) {
                        $padded3 = str_pad(ltrim($code, '0'), 3, '0', STR_PAD_LEFT);
                        $name = $locationRows[$padded3] ?? '';
                    }
                    $locationMap[$code] = $name ?: $code;
                }
            }

            $data = $headers->map(function (TransactionHeader $header) use ($locationMap) {
                $purchaseAmount = (float) $header->transactionDetails()->sum('amount');
                $invoiceValue = (float) ($header->invoice_amount > 0 ? $header->invoice_amount : $header->net_total);

                return [
                    'date' => $header->transaction_date ? $header->transaction_date->format('Y-m-d') : ($header->document_date ? $header->document_date->format('Y-m-d') : ''),
                    'location' => $locationMap[$header->location] ?? '',
                    'supplier' => $header->supplier?->sup_name ?? '',
                    'grn_number' => $header->doc_no,
                    'invoice_number' => $header->invoice_no ?? '',
                    'purchase_type' => strtolower((string) ($header->payment_mode ?? '')),
                    'purchase_amount' => round($purchaseAmount, 2),
                    'vat' => round((float) $header->tax, 2),
                    'invoice_value' => round($invoiceValue, 2),
                ];
            })->values()->toArray();

            return Excel::download(
                new \App\Exports\SupplierWisePurchasingExport($data),
                'Supplier_Wise_Purchasing_Report.xlsx'
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export supplier wise purchasing report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function normalizeReportDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $trimmedValue = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmedValue)) {
            return $trimmedValue;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $trimmedValue)) {
            return Carbon::createFromFormat('d/m/Y', $trimmedValue)->format('Y-m-d');
        }

        return $trimmedValue;
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
            $stmt = $pdo->prepare("CALL sp_SalesReport(@pErrorCode, ?, ?, ?, ?, ?, ?)");
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
     * Get Web Sales Report — order-level only from checkouts and pick_and_collects.
     * No product-level details. Shows product_value, discount, sub_total,
     * courier_charge, cod_charge, net_total, and payment_type.
     */
    public function getWebSalesReport(Request $request)
    {
        try {
            $locaRaw = $request->input('location', '');
            $location = (trim($locaRaw) === '' || $locaRaw === 'ALL') ? '' : str_pad(ltrim($locaRaw, '0'), 3, '0', STR_PAD_LEFT);
            $dateFrom = $request->input('dateFrom', '');
            $dateTo = $request->input('dateTo', '');
            $type = strtoupper($request->input('type', ''));
            $paymentType = $request->input('payment_type', '');

            $cartDb = 'venpaa-cart';

            // ---- Date clause helper ----
            $buildDateClause = function ($col) use ($dateFrom, $dateTo) {
                $clause = '';
                $bindings = [];
                if ($dateFrom && $dateTo) {
                    $clause = "AND DATE({$col}) >= ? AND DATE({$col}) <= ?";
                    $bindings = [$dateFrom, $dateTo];
                } elseif ($dateFrom) {
                    $clause = "AND DATE({$col}) >= ?";
                    $bindings = [$dateFrom];
                } elseif ($dateTo) {
                    $clause = "AND DATE({$col}) <= ?";
                    $bindings = [$dateTo];
                }
                return [$clause, $bindings];
            };

            [$coDateClause, $coBindings] = $buildDateClause('co.created_at');
            [$pcDateClause, $pcBindings] = $buildDateClause('pc.created_at');

            // ---- 1. Fetch checkouts (delivery orders) ----
            $checkoutSql = "
                SELECT
                    co.id,
                    co.order_id           AS doc_no,
                    co.created_at          AS transaction_date,
                    co.type,
                    co.type_name,
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

            // ---- 2. Fetch pick & collect orders (grouped by pick_and_collect_id) ----
            $pcSql = "
                SELECT
                    pc.pick_and_collect_id AS doc_no,
                    pc.type,
                    pc.type_name,
                    pc.location,
                    pc.location_name,
                    pc.created_at           AS transaction_date,
                    COUNT(*)                AS item_count,
                    SUM(pc.picked_qty)      AS total_qty,
                    SUM(pc.discount_amount) AS discount_amount,
                    SUM(pc.net_amount)      AS net_amount,
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
                GROUP BY pc.pick_and_collect_id, pc.type, pc.type_name, pc.location, pc.location_name, pc.created_at, pc.payment_status, u.id, u.fname, u.lname, u.email, u.phone, u.platform
                ORDER BY pc.created_at DESC
            ";
            $pcRows = DB::select($pcSql, $pcBindings);

            // ---- Helper: resolve payment type label ----
            $paymentTypeLabel = function ($t) {
                $map = [1 => 'COD', 2 => 'Card payment', 3 => 'Mintpay'];
                return $map[(int) $t] ?? 'Unknown';
            };

            // ---- Helper: resolve iid from platform ----
            $resolveIid = function ($platform) {
                return (in_array($platform, ['3', 'WEB', 'website', 'web'], true)) ? 'WEB' : 'APP';
            };

            // ---- 3. Build orders from checkouts ----
            $orders = [];
            $locationNamesPool = [];

            foreach ($checkoutRows as $co) {
                $payload = json_decode($co->payload, true);
                $totals = $payload['totals'] ?? [];

                $coLocRaw = $payload['location'] ?? '';
                $locationVal = $coLocRaw !== '' ? str_pad(ltrim($coLocRaw, '0'), 3, '0', STR_PAD_LEFT) : '';
                $platform = $co->platform ?? '';
                $iid = $resolveIid($platform);

                $items = $totals['items'] ?? $payload['items'] ?? [];

                $isCod = ((int) $co->type === 1);
                $productValue = (float) ($totals['originalSubTotal'] ?? $totals['subTotal'] ?? 0);
                $productDiscount = (float) ($totals['productDiscountTotal'] ?? 0);
                $couponDiscount = (float) ($totals['discountAmount'] ?? 0);
                $subTotal = (float) ($totals['subTotal'] ?? 0);
                $courierCharge = (float) ($totals['courierCharge'] ?? 0);
                $codCharge = $isCod ? (float) ($totals['codCharge'] ?? 0) : 0;
                $netTotal = $isCod
                    ? (float) ($totals['netTotalWithCod'] ?? $subTotal + $courierCharge + $codCharge)
                    : (float) ($totals['netTotalWithoutCod'] ?? $subTotal + $courierCharge);

                $customerName = trim(($co->fname ?? '') . ' ' . ($co->lname ?? '')) ?: '-';

                // Filter by type (WEB/APP)
                if ($type && $type !== 'ALL' && $iid !== $type) {
                    continue;
                }

                // Filter by payment_type
                if ($paymentType !== '' && $paymentType !== 'ALL' && (string) $co->type !== $paymentType) {
                    continue;
                }

                // Filter by location
                if ($location !== '' && $locationVal !== $location) {
                    continue;
                }

                $orders[] = [
                    'doc_no'            => (string) $co->doc_no,
                    'date'              => $co->transaction_date,
                    'customer_name'     => $customerName,
                    'email'             => $co->email ?? '',
                    'phone'             => $co->phone ?? '',
                    'source'            => 'CHECKOUT',
                    'location'          => $locationVal,
                    'payment_type'      => (int) $co->type,
                    'payment_type_name' => $paymentTypeLabel($co->type),
                    'iid'               => $iid,
                    'product_value'     => $productValue,
                    'discount'          => $productDiscount + $couponDiscount,
                    'sub_total'         => $subTotal,
                    'courier_charge'    => $courierCharge,
                    'cod_charge'        => $codCharge,
                    'net_total'         => $netTotal,
                    'item_count'        => count($items),
                    'total_qty'         => array_sum(array_column($items, 'quantity')),
                ];

                if ($locationVal && !isset($locationNamesPool[$locationVal])) {
                    $locationNamesPool[$locationVal] = true;
                }
            }

            // ---- 4. Build orders from pick & collect ----
            foreach ($pcRows as $pc) {
                $pcLocRaw = $pc->location ?? '';
                $locationVal = $pcLocRaw !== '' ? str_pad(ltrim($pcLocRaw, '0'), 3, '0', STR_PAD_LEFT) : '';
                $platform = $pc->platform ?? '';
                $iid = $resolveIid($platform);

                $netAmount = (float) ($pc->net_amount ?? 0);
                $discountAmount = (float) ($pc->discount_amount ?? 0);
                $subTotal = $netAmount + $discountAmount;
                $customerName = trim(($pc->fname ?? '') . ' ' . ($pc->lname ?? '')) ?: '-';

                // Filter by type (WEB/APP)
                if ($type && $type !== 'ALL' && $iid !== $type) {
                    continue;
                }

                // Filter by payment_type
                if ($paymentType !== '' && $paymentType !== 'ALL' && (string) $pc->type !== $paymentType) {
                    continue;
                }

                // Filter by location
                if ($location !== '' && $locationVal !== $location) {
                    continue;
                }

                $orders[] = [
                    'doc_no'            => (string) $pc->doc_no,
                    'date'              => $pc->transaction_date,
                    'customer_name'     => $customerName,
                    'email'             => $pc->email ?? '',
                    'phone'             => $pc->phone ?? '',
                    'source'            => 'PICK_AND_COLLECT',
                    'location'          => $locationVal,
                    'location_name'     => $pc->location_name ?? '',
                    'payment_type'      => (int) $pc->type,
                    'payment_type_name' => $paymentTypeLabel($pc->type),
                    'iid'               => $iid,
                    'product_value'     => $subTotal,
                    'discount'          => $discountAmount,
                    'sub_total'         => $subTotal,
                    'courier_charge'    => 0,
                    'cod_charge'        => 0,
                    'net_total'         => $netAmount,
                    'item_count'        => (int) ($pc->item_count ?? 1),
                    'total_qty'         => (float) ($pc->total_qty ?? 0),
                ];

                if ($locationVal && !isset($locationNamesPool[$locationVal])) {
                    $locationNamesPool[$locationVal] = true;
                }
            }

            // ---- 5. Enrich location names ----
            $locaCodes = array_keys($locationNamesPool);
            $locationNames = [];
            if (!empty($locaCodes)) {
                $locationNames = DB::table('locations')
                    ->whereIn('loca_code', $locaCodes)
                    ->pluck('loca_name', 'loca_code')
                    ->toArray();
            }

            foreach ($orders as &$order) {
                $lc = $order['location'];
                $order['location_name'] = $locationNames[$lc] ?? '';
            }
            unset($order);

            // ---- 6. Compute totals ----
            $totalProductValue = 0;
            $totalDiscount = 0;
            $totalSubTotal = 0;
            $totalCourierCharge = 0;
            $totalCodCharge = 0;
            $totalNetTotal = 0;

            foreach ($orders as $o) {
                $totalProductValue += $o['product_value'];
                $totalDiscount += $o['discount'];
                $totalSubTotal += $o['sub_total'];
                $totalCourierCharge += $o['courier_charge'];
                $totalCodCharge += $o['cod_charge'];
                $totalNetTotal += $o['net_total'];
            }

            return response()->json([
                'success' => true,
                'message' => 'Web sales report fetched successfully',
                'data' => [
                    'orders' => $orders,
                    'totals' => [
                        'total_product_value' => $totalProductValue,
                        'total_discount'      => $totalDiscount,
                        'total_sub_total'     => $totalSubTotal,
                        'total_courier_charge' => $totalCourierCharge,
                        'total_cod_charge'    => $totalCodCharge,
                        'total_net_total'     => $totalNetTotal,
                        'order_count'         => count($orders),
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
            $stmt = $pdo->prepare("CALL sp_SalesReport(@pErrorCode, ?, ?, ?, ?, ?, ?)");
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

    public function exportWebSalesReport(Request $request)
    {
        try {
            $reportResponse = $this->getWebSalesReport($request);
            $reportData = $reportResponse->getData(true);

            if (!$reportData['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch web sales report',
                ], 400);
            }

            $orders = $reportData['data']['orders'] ?? [];
            $totals = $reportData['data']['totals'] ?? null;

            return Excel::download(
                new WebSalesReportExport($orders, $totals),
                'Web_Sales_Report.xlsx'
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export web sales report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
