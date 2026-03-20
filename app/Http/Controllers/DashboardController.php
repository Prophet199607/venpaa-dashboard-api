<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\TransactionHeader;
use Illuminate\Support\Facades\DB;

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
}
