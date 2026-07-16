<?php

namespace App\Http\Controllers;

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
            $days = (int) $request->input('days', 14);
            $location = $request->input('location', '');

            $daily001 = DB::table('stock_masters')
                ->select(DB::raw('DATE(transaction_date) as date'), DB::raw('SUM(amount) as amount'))
                ->where('iid', '001')
                ->whereNotNull('transaction_date')
                ->when($location, function ($q) use ($location) {
                    return $q->where('location', $location);
                })
                ->where('transaction_date', '>=', today()->subDays($days))
                ->groupBy(DB::raw('DATE(transaction_date)'))
                ->orderBy('date')
                ->get()
                ->keyBy('date')
                ->map(function ($row) {
                    return ['date' => $row->date, 'amount' => (float) $row->amount];
                });

            $dailyONL = DB::table('stock_masters')
                ->select(DB::raw('DATE(transaction_date) as date'), DB::raw('SUM(amount) as amount'))
                ->where('iid', 'ONL')
                ->whereNotNull('transaction_date')
                ->when($location, function ($q) use ($location) {
                    return $q->where('location', $location);
                })
                ->where('transaction_date', '>=', today()->subDays($days))
                ->groupBy(DB::raw('DATE(transaction_date)'))
                ->orderBy('date')
                ->get()
                ->keyBy('date')
                ->map(function ($row) {
                    return ['date' => $row->date, 'amount' => (float) $row->amount];
                });

            $allPosDates = collect(array_merge(
                $daily001->keys()->toArray(),
                $dailyONL->keys()->toArray()
            ))->unique()->sort()->values();

            $posDaily = $allPosDates->map(function ($date) use ($daily001, $dailyONL) {
                $has001 = isset($daily001[$date]);
                return [
                    'date'   => $date,
                    'amount' => $has001
                        ? (float) ($daily001[$date]['amount'] ?? 0)
                        : (float) ($dailyONL[$date]['amount'] ?? 0),
                ];
            })->keyBy('date');

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

            $cartDb = 'venpaa-cart';
            $onlineTotalRevenue = 0;
            $onlineTodayRevenue = 0;
            $onlineTotalOrders = 0;
            $onlineTodayOrders = 0;
            $sourceBreakdown = ['WEB' => ['orders' => 0, 'revenue' => 0], 'APP' => ['orders' => 0, 'revenue' => 0]];
            $onlineDaily = [];

            try {
                $checkouts = DB::connection()->select("
                    SELECT co.id, co.created_at, co.payload, co.type, u.platform
                    FROM `{$cartDb}`.checkouts co
                    JOIN `{$cartDb}`.users u ON co.user_id = u.id
                    WHERE co.payment_status = 'success'
                    AND co.created_at >= ?
                ", [today()->subDays($days)]);

                foreach ($checkouts as $co) {
                    $payload = json_decode($co->payload, true);
                    $totals = $payload['totals'] ?? [];
                    $isCod = ((int) $co->type === 1);
                    $subTotal = (float) ($totals['subTotal'] ?? 0);
                    $courierCharge = (float) ($totals['courierCharge'] ?? 0);
                    $codCharge = $isCod ? (float) ($totals['codCharge'] ?? 0) : 0;
                    $netTotal = $isCod
                        ? (float) ($totals['netTotalWithCod'] ?? $subTotal + $courierCharge + $codCharge)
                        : (float) ($totals['netTotalWithoutCod'] ?? $subTotal + $courierCharge);
                    $platform = strtoupper((string) ($co->platform ?? ''));
                    $source = in_array($platform, ['3', 'WEB', 'WEBSITE']) ? 'WEB' : 'APP';
                    $date = date('Y-m-d', strtotime($co->created_at));

                    $onlineTotalRevenue += $netTotal;
                    $onlineTotalOrders++;
                    $sourceBreakdown[$source]['orders']++;
                    $sourceBreakdown[$source]['revenue'] += $netTotal;

                    if (date('Y-m-d', strtotime($co->created_at)) === today()->format('Y-m-d')) {
                        $onlineTodayRevenue += $netTotal;
                        $onlineTodayOrders++;
                    }

                    if (!isset($onlineDaily[$date])) {
                        $onlineDaily[$date] = ['date' => $date, 'revenue' => 0];
                    }
                    $onlineDaily[$date]['revenue'] += $netTotal;
                }

                $pcOrders = DB::connection()->select("
                    SELECT pc.pick_and_collect_id, pc.created_at, pc.type, u.platform, pc.net_amount
                    FROM `{$cartDb}`.pick_and_collects pc
                    JOIN `{$cartDb}`.users u ON pc.user_id = u.id
                    WHERE pc.payment_status = 'success'
                    AND pc.created_at >= ?
                    GROUP BY pc.pick_and_collect_id
                ", [today()->subDays($days)]);

                foreach ($pcOrders as $pc) {
                    $netAmount = (float) ($pc->net_amount ?? 0);
                    $platform = strtoupper((string) ($pc->platform ?? ''));
                    $source = in_array($platform, ['3', 'WEB', 'WEBSITE']) ? 'WEB' : 'APP';
                    $date = date('Y-m-d', strtotime($pc->created_at));

                    $onlineTotalRevenue += $netAmount;
                    $onlineTotalOrders++;
                    $sourceBreakdown[$source]['orders']++;
                    $sourceBreakdown[$source]['revenue'] += $netAmount;

                    if (date('Y-m-d', strtotime($pc->created_at)) === today()->format('Y-m-d')) {
                        $onlineTodayRevenue += $netAmount;
                        $onlineTodayOrders++;
                    }

                    if (!isset($onlineDaily[$date])) {
                        $onlineDaily[$date] = ['date' => $date, 'revenue' => 0];
                    }
                    $onlineDaily[$date]['revenue'] += $netAmount;
                }
            } catch (\Exception $e) {
            }

            $allDates = collect(array_merge(
                $posDaily->keys()->toArray(),
                array_keys($onlineDaily)
            ))->unique()->sort()->values();

            $mergedDaily = $allDates->map(function ($date) use ($posDaily, $onlineDaily) {
                return [
                    'date'           => $date,
                    'pos_amount'     => (float) ($posDaily[$date]['amount'] ?? 0),
                    'online_revenue' => (float) ($onlineDaily[$date]['revenue'] ?? 0),
                ];
            });

            $combinedTotal = $totalPos + $onlineTotalRevenue;
            $posPct = $combinedTotal > 0 ? round(($totalPos / $combinedTotal) * 100, 1) : 0;
            $onlinePct = $combinedTotal > 0 ? round(($onlineTotalRevenue / $combinedTotal) * 100, 1) : 0;

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
                    'daily_trend' => $mergedDaily,
                    'combined' => [
                        'total_revenue'    => $combinedTotal,
                        'pos_percentage'   => $posPct,
                        'online_percentage'=> $onlinePct,
                        'pos_revenue'      => $totalPos,
                        'online_revenue'   => $onlineTotalRevenue,
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales overview',
                'error' => $e->getMessage(),
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
