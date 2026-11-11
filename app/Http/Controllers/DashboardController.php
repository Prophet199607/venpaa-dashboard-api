<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Author;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Category;
use App\Models\Publisher;
use Illuminate\Http\Request;
use App\Models\CodValueCharge;
use App\Models\TransactionHeader;
use Illuminate\Support\Facades\DB;
use App\Models\CourierWeightCharge;

class DashboardController extends Controller
{
    public function getStats()
    {
        try {
            // Main Top Stats
            $totalBooks = Product::where('department', '10')->count();
            $totalTransactions = TransactionHeader::count();

            // Extra Stats
            $totalAuthors = Author::count();
            $totalSuppliers = Supplier::count();
            $totalCustomers = Customer::count();
            $totalCategories = Category::count();
            $totalPublishers = Publisher::count();

            // Recent Transactions
            $recentBackoffice = TransactionHeader::with('location')
                ->latest()
                ->take(6)
                ->get()
                ->map(function($trans) {
                    return [
                        'doc_no' => $trans->doc_no,
                        'description' => $trans->remarks_ref ?: 'Backoffice Transaction',
                        'type' => $trans->iid,
                        'total' => $trans->net_total,
                        'status' => $trans->approval_status,
                        'date' => $trans->created_at->diffForHumans()
                    ];
                });

            // Top Selling Products
            $topProductData = DB::table('tbl_OnlineStockFrom_Pos')
                ->select('Prod_Code', DB::raw('COUNT(*) as count'))
                ->groupBy('Prod_Code')
                ->orderBy('count', 'desc')
                ->take(5)
                ->get();

            $topProductCodes = $topProductData->pluck('Prod_Code');
            $products = Product::whereIn('prod_code', $topProductCodes)->get()->keyBy('prod_code');

            $topProducts = $topProductData->map(function($item) use ($products) {
                $product = $products->get($item->Prod_Code);
                return [
                    'prod_code' => $item->Prod_Code,
                    'Item_Descrip' => $product ? $product->prod_name : 'Unknown Product',
                    'total_qty' => $item->count,
                    'total_amount' => $product ? ($product->selling_price * $item->count) : 0
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'total_books' => [
                            'value' => $totalBooks,
                        ],
                        'total_transactions' => [
                            'value' => $totalTransactions,
                        ],
                    ],
                    'extra_stats' => [
                        'authors' => $totalAuthors,
                        'suppliers' => $totalSuppliers,
                        'publishers' => $totalPublishers,
                        'categories' => $totalCategories,
                        'customers' => $totalCustomers
                    ],
                    'recent_orders' => $recentBackoffice,
                    'top_products' => $topProducts,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function calculateCharges(Request $request)
    {
        try {
            $totalValue = $request->input('total_value', 0);
            $totalWeight = $request->input('total_weight', 0);

            // Find COD charge
            $codCharge = CodValueCharge::where('value_from', '<=', $totalValue)
                ->where('value_to', '>=', $totalValue)
                ->first();

            // Find Courier charge
            $courierCharge = CourierWeightCharge::where('weight_from', '<=', $totalWeight)
                ->where('weight_to', '>=', $totalWeight)
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'cod_charge' => $codCharge ? $codCharge->charge : 0,
                    'courier_charge' => $courierCharge ? $courierCharge->charge : 0,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate charges',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getBills(Request $request)
    {
        try {
            $locaRaw  = $request->input('location', $request->input('Loca', ''));
            $location = $locaRaw !== ''
                ? str_pad(ltrim((string) $locaRaw, '0'), 2, '0', STR_PAD_LEFT)
                : '';

            $dateFrom = trim($request->input('date_from', ''));
            $dateTo   = trim($request->input('date_to',   ''));
            $unit     = trim($request->input('unit',      ''));

            if ($location === '' || $dateFrom === '' || $unit === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required parameters: location, date_from, and unit are required.',
                ], 400);
            }

            if (!ctype_digit($unit) || (int) $unit < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid unit value. Must be a positive integer.',
                ], 400);
            }

            $dateFrom = self::normaliseDateForSP($dateFrom);
            if ($dateFrom === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date_from format. Expected yyyy-MM-dd or dd/MM/yyyy.',
                ], 400);
            }

            if ($dateTo === '') {
                $dateTo = $dateFrom;
            } else {
                $dateTo = self::normaliseDateForSP($dateTo);
                if ($dateTo === null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date_to format. Expected yyyy-MM-dd or dd/MM/yyyy.',
                    ], 400);
                }
            }

            $payTypeRaw  = $request->input('pay_type', null);
            $payCategory = (isset($payTypeRaw) && $payTypeRaw !== '' && $payTypeRaw !== 'ALL')
                ? strtoupper(trim($payTypeRaw))
                : null;

            $paymentMethodRaw = $request->input('payment_method', null);
            $paymentMethod    = (isset($paymentMethodRaw) && $paymentMethodRaw !== '' && $paymentMethodRaw !== 'ALL')
                ? strtoupper(trim($paymentMethodRaw))
                : null;

            $bills = DB::select('CALL sp_GetBills(?, ?, ?, ?, ?, ?)', [
                $location,
                $dateFrom,
                $dateTo,
                (string)(int) $unit,
                $payCategory,
                $paymentMethod,
            ]);

            $parsed = collect($bills)->map(function ($row) {
                $row->items = json_decode($row->items ?? '[]', true) ?? [];
                return $row;
            });

            return response()->json([
                'success' => true,
                'data'    => $parsed,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bills.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getSalesOverview(Request $request)
    {
        try {
            $location   = $request->input('location', '');
            $filterType = $request->input('filter_type', $request->input('view_mode', 'month_wise'));
            if (!in_array($filterType, ['month_wise', 'year_wise', 'day_wise'])) {
                $filterType = 'month_wise';
            }

            $period        = $request->input('period', '12_months');
            $selectedYear  = $request->input('year', null);
            $selectedMonth = $request->input('month', null);

            $now = Carbon::now();
            $currentYear = (int) $now->year;

            // Determine date range & initialize buckets
            $buckets = [];

            if ($filterType === 'month_wise') {
                if ($period === '12_months' || (!$selectedYear && $period === '12_months')) {
                    // Default: Rolling entire year related to current month (e.g. 2025 Nov to 2026 Nov - 13 months)
                    $startDate = $now->copy()->subMonths(12)->startOfMonth();
                    $endDate   = $now->copy()->endOfMonth();

                    $cursor = $startDate->copy();
                    while ($cursor <= $endDate) {
                        $k = $cursor->format('Y-m');
                        $buckets[$k] = [
                            'key'            => $k,
                            'date'           => $cursor->format('Y-m-d'),
                            'label'          => $cursor->format('M Y'),
                            'short_label'    => $cursor->format('M y'),
                            'pos_amount'     => 0.0,
                            'online_revenue' => 0.0,
                            'total_revenue'  => 0.0,
                        ];
                        $cursor->addMonth();
                    }
                } else {
                    // Specific year (e.g. 2026, 2025)
                    $yearNum   = (int) ($selectedYear ?: $period);
                    $startDate = Carbon::create($yearNum, 1, 1, 0, 0, 0)->startOfMonth();
                    $endDate   = Carbon::create($yearNum, 12, 31, 23, 59, 59)->endOfMonth();

                    for ($m = 1; $m <= 12; $m++) {
                        $cursor = Carbon::create($yearNum, $m, 1, 0, 0, 0);
                        $k = $cursor->format('Y-m');
                        $buckets[$k] = [
                            'key'            => $k,
                            'date'           => $cursor->format('Y-m-d'),
                            'label'          => $cursor->format('M Y'),
                            'short_label'    => $cursor->format('M y'),
                            'pos_amount'     => 0.0,
                            'online_revenue' => 0.0,
                            'total_revenue'  => 0.0,
                        ];
                    }
                }
            } elseif ($filterType === 'year_wise') {
                // Year-wise: e.g. last 5 years up to current year
                $startYearNum = $currentYear - 4;
                $startDate    = Carbon::create($startYearNum, 1, 1, 0, 0, 0);
                $endDate      = $now->copy()->endOfYear();

                for ($y = $startYearNum; $y <= $currentYear; $y++) {
                    $k = (string) $y;
                    $buckets[$k] = [
                        'key'            => $k,
                        'date'           => "{$y}-01-01",
                        'label'          => $k,
                        'short_label'    => $k,
                        'pos_amount'     => 0.0,
                        'online_revenue' => 0.0,
                        'total_revenue'  => 0.0,
                    ];
                }
            } else { // 'day_wise'
                if ($selectedMonth && preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
                    $startMonthDate = Carbon::parse($selectedMonth . '-01')->startOfMonth();
                    $endMonthDate   = $startMonthDate->copy()->endOfMonth();
                    $startDate      = $startMonthDate;
                    $endDate        = $endMonthDate;
                } else {
                    $days      = (int) $request->input('days', 30);
                    $startDate = $now->copy()->subDays($days - 1)->startOfDay();
                    $endDate   = $now->copy()->endOfDay();
                }

                $cursor = $startDate->copy();
                while ($cursor <= $endDate) {
                    $k = $cursor->format('Y-m-d');
                    $buckets[$k] = [
                        'key'            => $k,
                        'date'           => $k,
                        'label'          => $cursor->format('d M Y'),
                        'short_label'    => $cursor->format('d M'),
                        'pos_amount'     => 0.0,
                        'online_revenue' => 0.0,
                        'total_revenue'  => 0.0,
                    ];
                    $cursor->addDay();
                }
            }

            $startDateStr = $startDate->format('Y-m-d 00:00:00');
            $endDateStr   = $endDate->format('Y-m-d 23:59:59');

            // ── Query POS (stock_masters) ──────────────────────────────────
            $daily001 = DB::table('stock_masters')
                ->select(DB::raw('DATE(transaction_date) as date'), DB::raw('SUM(amount) as amount'))
                ->where('iid', '001')
                ->whereNotNull('transaction_date')
                ->when($location, function ($q) use ($location) {
                    return $q->where('location', $location);
                })
                ->whereBetween('transaction_date', [$startDateStr, $endDateStr])
                ->groupBy(DB::raw('DATE(transaction_date)'))
                ->get()
                ->keyBy('date');

            $dailyONL = DB::table('stock_masters')
                ->select(DB::raw('DATE(transaction_date) as date'), DB::raw('SUM(amount) as amount'))
                ->where('iid', 'ONL')
                ->whereNotNull('transaction_date')
                ->when($location, function ($q) use ($location) {
                    return $q->where('location', $location);
                })
                ->whereBetween('transaction_date', [$startDateStr, $endDateStr])
                ->groupBy(DB::raw('DATE(transaction_date)'))
                ->get()
                ->keyBy('date');

            $allPosDates = collect(array_merge(
                $daily001->keys()->toArray(),
                $dailyONL->keys()->toArray()
            ))->unique()->values();

            foreach ($allPosDates as $dateStr) {
                $has001 = isset($daily001[$dateStr]);
                $amount = $has001
                    ? (float) ($daily001[$dateStr]->amount ?? 0)
                    : (float) ($dailyONL[$dateStr]->amount ?? 0);

                if ($filterType === 'month_wise') {
                    $bKey = substr($dateStr, 0, 7);
                } elseif ($filterType === 'year_wise') {
                    $bKey = substr($dateStr, 0, 4);
                } else {
                    $bKey = $dateStr;
                }

                if (isset($buckets[$bKey])) {
                    $buckets[$bKey]['pos_amount'] += $amount;
                }
            }

            // Summary stats for POS
            $totalPos001 = (float) DB::table('stock_masters')
                ->where('iid', '001')
                ->when($location, function ($q) use ($location) {
                    return $q->where('location', $location);
                })
                ->sum('amount');
            $todayPos001 = (float) DB::table('stock_masters')
                ->where('iid', '001')
                ->when($location, function ($q) use ($location) {
                    return $q->where('location', $location);
                })
                ->whereDate('transaction_date', today())
                ->sum('amount');
            $todayPosONL = (float) DB::table('stock_masters')
                ->where('iid', 'ONL')
                ->when($location, function ($q) use ($location) {
                    return $q->where('location', $location);
                })
                ->whereDate('transaction_date', today())
                ->sum('amount');

            $totalPos = $totalPos001 + $todayPosONL;
            $todayPos = $todayPos001 > 0 ? $todayPos001 : $todayPosONL;

            // ── Query Online (venpaa-cart) ─────────────────────────────────
            $cartDb = env('DB_CART_DATABASE', 'venpaa-cart');
            $onlineTotalRevenue = 0;
            $onlineTodayRevenue = 0;
            $onlineTotalOrders   = 0;
            $onlineTodayOrders   = 0;
            $sourceBreakdown = ['WEB' => ['orders' => 0, 'revenue' => 0], 'APP' => ['orders' => 0, 'revenue' => 0]];

            try {
                $checkouts = DB::connection()->select("
                    SELECT co.id, co.created_at, co.payload, co.type, u.platform
                    FROM `{$cartDb}`.checkouts co
                    JOIN `{$cartDb}`.users u ON co.user_id = u.id
                    WHERE co.payment_status = 'success'
                    AND co.created_at BETWEEN ? AND ?
                ", [$startDateStr, $endDateStr]);

                foreach ($checkouts as $co) {
                    $payload       = json_decode($co->payload, true);
                    $totals        = $payload['totals'] ?? [];
                    $isCod         = ((int) $co->type === 1);
                    $subTotal      = (float) ($totals['subTotal'] ?? 0);
                    $courierCharge = (float) ($totals['courierCharge'] ?? 0);
                    $codCharge     = $isCod ? (float) ($totals['codCharge'] ?? 0) : 0;
                    $netTotal      = $isCod
                        ? (float) ($totals['netTotalWithCod'] ?? $subTotal + $courierCharge + $codCharge)
                        : (float) ($totals['netTotalWithoutCod'] ?? $subTotal + $courierCharge);
                    $platform      = strtoupper((string) ($co->platform ?? ''));
                    $source        = in_array($platform, ['3', 'WEB', 'WEBSITE']) ? 'WEB' : 'APP';
                    $dateStr       = date('Y-m-d', strtotime($co->created_at));

                    $onlineTotalRevenue += $netTotal;
                    $onlineTotalOrders++;
                    $sourceBreakdown[$source]['orders']++;
                    $sourceBreakdown[$source]['revenue'] += $netTotal;

                    if ($dateStr === today()->format('Y-m-d')) {
                        $onlineTodayRevenue += $netTotal;
                        $onlineTodayOrders++;
                    }

                    if ($filterType === 'month_wise') {
                        $bKey = substr($dateStr, 0, 7);
                    } elseif ($filterType === 'year_wise') {
                        $bKey = substr($dateStr, 0, 4);
                    } else {
                        $bKey = $dateStr;
                    }

                    if (isset($buckets[$bKey])) {
                        $buckets[$bKey]['online_revenue'] += $netTotal;
                    }
                }

                $pcOrders = DB::connection()->select("
                    SELECT pc.pick_and_collect_id, pc.created_at, pc.type, u.platform, pc.net_amount
                    FROM `{$cartDb}`.pick_and_collects pc
                    JOIN `{$cartDb}`.users u ON pc.user_id = u.id
                    WHERE pc.payment_status = 'success'
                    AND pc.created_at BETWEEN ? AND ?
                    GROUP BY pc.pick_and_collect_id
                ", [$startDateStr, $endDateStr]);

                foreach ($pcOrders as $pc) {
                    $netAmount = (float) ($pc->net_amount ?? 0);
                    $platform  = strtoupper((string) ($pc->platform ?? ''));
                    $source    = in_array($platform, ['3', 'WEB', 'WEBSITE']) ? 'WEB' : 'APP';
                    $dateStr   = date('Y-m-d', strtotime($pc->created_at));

                    $onlineTotalRevenue += $netAmount;
                    $onlineTotalOrders++;
                    $sourceBreakdown[$source]['orders']++;
                    $sourceBreakdown[$source]['revenue'] += $netAmount;

                    if ($dateStr === today()->format('Y-m-d')) {
                        $onlineTodayRevenue += $netAmount;
                        $onlineTodayOrders++;
                    }

                    if ($filterType === 'month_wise') {
                        $bKey = substr($dateStr, 0, 7);
                    } elseif ($filterType === 'year_wise') {
                        $bKey = substr($dateStr, 0, 4);
                    } else {
                        $bKey = $dateStr;
                    }

                    if (isset($buckets[$bKey])) {
                        $buckets[$bKey]['online_revenue'] += $netAmount;
                    }
                }
            } catch (\Exception $e) {
            }

            // Calculate totals and period stats
            $periodPosTotal    = 0;
            $periodOnlineTotal = 0;

            foreach ($buckets as $k => $item) {
                $posVal = round($item['pos_amount'], 2);
                $onlVal = round($item['online_revenue'], 2);
                $totVal = round($posVal + $onlVal, 2);

                $buckets[$k]['pos_amount']     = $posVal;
                $buckets[$k]['online_revenue'] = $onlVal;
                $buckets[$k]['total_revenue']  = $totVal;

                $periodPosTotal    += $posVal;
                $periodOnlineTotal += $onlVal;
            }

            $periodTotalRevenue = round($periodPosTotal + $periodOnlineTotal, 2);
            $periodPosPct       = $periodTotalRevenue > 0 ? round(($periodPosTotal / $periodTotalRevenue) * 100, 1) : 0;
            $periodOnlinePct    = $periodTotalRevenue > 0 ? round(($periodOnlineTotal / $periodTotalRevenue) * 100, 1) : 0;

            $combinedTotal = $totalPos + $onlineTotalRevenue;
            $posPct        = $combinedTotal > 0 ? round(($totalPos / $combinedTotal) * 100, 1) : 0;
            $onlinePct     = $combinedTotal > 0 ? round(($onlineTotalRevenue / $combinedTotal) * 100, 1) : 0;

            // Compute available years for dropdown
            $minPosYear = DB::table('stock_masters')
                ->whereNotNull('transaction_date')
                ->where('iid', '001')
                ->min(DB::raw('YEAR(transaction_date)'));
            $earliestYear   = $minPosYear ? max((int) $minPosYear, $currentYear - 6) : ($currentYear - 3);
            $availableYears = range($currentYear, $earliestYear);

            $trendList = array_values($buckets);

            // ── 14-Day Daily Trend Calculation (for 14-days chart) ───────
            $fourteenStartDate = Carbon::today()->subDays(13)->startOfDay()->format('Y-m-d 00:00:00');
            $fourteenEndDate   = Carbon::today()->endOfDay()->format('Y-m-d 23:59:59');

            $pos14_001 = DB::table('stock_masters')
                ->select(DB::raw('DATE(transaction_date) as date'), DB::raw('SUM(amount) as amount'))
                ->where('iid', '001')
                ->whereNotNull('transaction_date')
                ->when($location, function ($q) use ($location) {
                    return $q->where('location', $location);
                })
                ->whereBetween('transaction_date', [$fourteenStartDate, $fourteenEndDate])
                ->groupBy(DB::raw('DATE(transaction_date)'))
                ->get()
                ->keyBy('date');

            $pos14_ONL = DB::table('stock_masters')
                ->select(DB::raw('DATE(transaction_date) as date'), DB::raw('SUM(amount) as amount'))
                ->where('iid', 'ONL')
                ->whereNotNull('transaction_date')
                ->when($location, function ($q) use ($location) {
                    return $q->where('location', $location);
                })
                ->whereBetween('transaction_date', [$fourteenStartDate, $fourteenEndDate])
                ->groupBy(DB::raw('DATE(transaction_date)'))
                ->get()
                ->keyBy('date');

            $online14Daily = [];
            try {
                $co14 = DB::connection()->select("
                    SELECT co.id, co.created_at, co.payload, co.type
                    FROM `{$cartDb}`.checkouts co
                    WHERE co.payment_status = 'success'
                    AND co.created_at BETWEEN ? AND ?
                ", [$fourteenStartDate, $fourteenEndDate]);

                foreach ($co14 as $co) {
                    $payload       = json_decode($co->payload, true);
                    $totals        = $payload['totals'] ?? [];
                    $isCod         = ((int) $co->type === 1);
                    $subTotal      = (float) ($totals['subTotal'] ?? 0);
                    $courierCharge = (float) ($totals['courierCharge'] ?? 0);
                    $codCharge     = $isCod ? (float) ($totals['codCharge'] ?? 0) : 0;
                    $netTotal      = $isCod
                        ? (float) ($totals['netTotalWithCod'] ?? $subTotal + $courierCharge + $codCharge)
                        : (float) ($totals['netTotalWithoutCod'] ?? $subTotal + $courierCharge);
                    $d = date('Y-m-d', strtotime($co->created_at));
                    $online14Daily[$d] = ($online14Daily[$d] ?? 0) + $netTotal;
                }

                $pc14 = DB::connection()->select("
                    SELECT pc.created_at, pc.net_amount
                    FROM `{$cartDb}`.pick_and_collects pc
                    WHERE pc.payment_status = 'success'
                    AND pc.created_at BETWEEN ? AND ?
                ", [$fourteenStartDate, $fourteenEndDate]);

                foreach ($pc14 as $pc) {
                    $d = date('Y-m-d', strtotime($pc->created_at));
                    $online14Daily[$d] = ($online14Daily[$d] ?? 0) + (float) ($pc->net_amount ?? 0);
                }
            } catch (\Exception $e) {
            }

            $fourteenDaysList = [];
            $cur14 = Carbon::today()->subDays(13);
            while ($cur14 <= Carbon::today()) {
                $dStr = $cur14->format('Y-m-d');
                $has001 = isset($pos14_001[$dStr]);
                $pAmt = $has001 ? (float) ($pos14_001[$dStr]->amount ?? 0) : (float) ($pos14_ONL[$dStr]->amount ?? 0);
                $oAmt = (float) ($online14Daily[$dStr] ?? 0);

                $fourteenDaysList[] = [
                    'date'           => $dStr,
                    'label'          => $cur14->format('d M'),
                    'pos_amount'     => round($pAmt, 2),
                    'online_revenue' => round($oAmt, 2),
                    'total_revenue'  => round($pAmt + $oAmt, 2),
                ];
                $cur14->addDay();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'pos' => [
                        'today_sales' => $todayPos,
                        'total_sales' => $totalPos,
                    ],
                    'online' => [
                        'today_orders'     => $onlineTodayOrders,
                        'today_revenue'    => $onlineTodayRevenue,
                        'total_orders'     => $onlineTotalOrders,
                        'total_revenue'    => $onlineTotalRevenue,
                        'source_breakdown' => $sourceBreakdown,
                    ],
                    'daily_trend'         => $fourteenDaysList, // 14-days daily trend for 14-days chart
                    'fourteen_days_trend' => $fourteenDaysList,
                    'trend'               => $trendList,        // Filtered trend for the full-width revenue chart
                    'combined' => [
                        'total_revenue'    => $periodTotalRevenue > 0 ? $periodTotalRevenue : $combinedTotal,
                        'pos_percentage'   => $periodPosPct,
                        'online_percentage'=> $periodOnlinePct,
                        'pos_revenue'      => $periodPosTotal,
                        'online_revenue'   => $periodOnlineTotal,
                        'all_time_pos'     => $totalPos,
                        'all_time_online'  => $onlineTotalRevenue,
                        'all_time_total'   => $combinedTotal,
                    ],
                    'period_summary' => [
                        'total_revenue'    => $periodTotalRevenue,
                        'pos_revenue'      => $periodPosTotal,
                        'online_revenue'   => $periodOnlineTotal,
                        'pos_percentage'   => $periodPosPct,
                        'online_percentage'=> $periodOnlinePct,
                        'count'            => count($trendList),
                        'average'          => count($trendList) > 0 ? round($periodTotalRevenue / count($trendList), 2) : 0,
                    ],
                    'filter' => [
                        'filter_type'     => $filterType,
                        'period'          => $period,
                        'year'            => $selectedYear,
                        'month'           => $selectedMonth,
                        'available_years' => array_values($availableYears),
                        'start_date'      => substr($startDateStr, 0, 10),
                        'end_date'        => substr($endDateStr, 0, 10),
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales overview',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Accept yyyy-MM-dd or dd/MM/yyyy, always return dd/MM/yyyy.
     * Returns null on unrecognised format.
     */
    private static function normaliseDateForSP(string $date): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return date('d/m/Y', strtotime($date));
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return $date;
        }
        return null;
    }
}
