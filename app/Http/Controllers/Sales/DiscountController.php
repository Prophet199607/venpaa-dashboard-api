<?php

namespace App\Http\Controllers\Sales;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\ProductDiscountLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class DiscountController extends Controller
{
    public function filter(Request $request)
    {
        try {
            $query = Product::query();

            if ($request->filled('department') && $request->department !== 'all') {
                $query->where('department', $request->department);
            }

            if ($request->filled('category') && $request->category !== 'all') {
                $query->where('category', $request->category);
            }

            if ($request->filled('sub_category') && $request->sub_category !== 'all') {
                $query->where('sub_category', $request->sub_category);
            }

            $perPage = $request->input('per_page', 10);
            $products = $query->select('prod_code', 'prod_name', 'selling_price', 'discount', 'dis_per', 'dis_start_date', 'dis_end_date')
                              ->orderBy('prod_code')
                              ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Filter Products Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products'
            ], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            if ($request->has('updates') && is_array($request->updates)) {
                $updates = $request->updates;
            } else {
                $request->validate([
                    'prod_codes' => 'required|array',
                    'discount' => 'required|numeric|min:0',
                    'dis_per' => 'required|numeric|min:0|max:100',
                    'dis_start_date' => 'nullable|string',
                    'dis_end_date' => 'nullable|string',
                ]);
                $updates = [[
                    'prod_codes' => $request->prod_codes,
                    'discount' => $request->discount,
                    'dis_per' => $request->dis_per,
                    'dis_start_date' => $request->dis_start_date,
                    'dis_end_date' => $request->dis_end_date,
                ]];
            }

            DB::transaction(function () use ($updates) {
                foreach ($updates as $update) {
                    $prod_codes = $update['prod_codes'];
                    $isRemoving = ($update['discount'] == 0 && $update['dis_per'] == 0);
                    $new_discount = $update['discount'];
                    $new_dis_per = $update['dis_per'];
                    $new_dis_start_date = $isRemoving ? null : ($update['dis_start_date'] ?? null);
                    $new_dis_end_date = $isRemoving ? null : ($update['dis_end_date'] ?? null);

                    $products = Product::whereIn('prod_code', $prod_codes)->get();

                    foreach ($products as $product) {
                        ProductDiscountLog::create([
                            'prod_code' => $product->prod_code,
                            'old_discount' => $product->discount,
                            'new_discount' => $new_discount,
                            'old_dis_per' => $product->dis_per,
                            'new_dis_per' => $new_dis_per,
                            'old_dis_start_date' => $product->dis_start_date,
                            'new_dis_start_date' => $new_dis_start_date,
                            'old_dis_end_date' => $product->dis_end_date,
                            'new_dis_end_date' => $new_dis_end_date,
                            'action' => $isRemoving ? 'removed' : (($product->discount == 0 && $product->dis_per == 0) ? 'created' : 'updated'),
                            'updated_by' => auth()->user() ? auth()->user()->name : 'System',
                        ]);

                        $product->update([
                            'discount' => $new_discount,
                            'dis_per' => $new_dis_per,
                            'dis_start_date' => $new_dis_start_date,
                            'dis_end_date' => $new_dis_end_date,
                        ]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Discounts updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Update Discounts Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update discounts: ' . $e->getMessage()
            ], 500);
        }
    }

    public function list()
    {
        try {
            $products = Product::where(function($q) {
                $q->where('discount', '>', 0)
                  ->orWhere('dis_per', '>', 0);
            })
            ->select('prod_code', 'prod_name', 'selling_price', 'discount', 'dis_per', 'dis_start_date', 'dis_end_date')
            ->orderBy('prod_code')
            ->get();
            // Dates are already stored as dd/mm/yyyy strings

            return response()->json([
                'success' => true,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            Log::error('List Discounted Products Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch discounted products'
            ], 500);
        }
    }
}
