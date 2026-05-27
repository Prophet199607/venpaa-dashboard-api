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
            $location = $locaRaw !== '' ? str_pad(ltrim((string) $locaRaw, '0'), 2, '0', STR_PAD_LEFT) : '';
            $date     = trim($request->input('date', ''));
            $unit     = trim($request->input('unit', ''));

            // ── Map frontend pay_type values to what the SP expects ───────────────
            $payTypeRaw = $request->input('pay_type', null);
            $payType    = (isset($payTypeRaw) && $payTypeRaw !== '' && $payTypeRaw !== 'ALL')
                            ? strtoupper(trim($payTypeRaw))
                            : null;

            // ── Validation ────────────────────────────────────────────────────────
            if ($location === '' || $date === '' || $unit === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required parameters: location, date, and unit are required.',
                ], 400);
            }

            // ── Normalise date: yyyy-MM-dd  →  dd/MM/yyyy (SP format) ─────────────
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('d/m/Y', strtotime($date));
            } elseif (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date format. Expected yyyy-MM-dd or dd/MM/yyyy.',
                ], 400);
            }

            $bills = DB::select('CALL sp_GetBills(?, ?, ?, ?)', [
                $location,
                $date,
                (string) (int) $unit,
                $payType,
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
}

