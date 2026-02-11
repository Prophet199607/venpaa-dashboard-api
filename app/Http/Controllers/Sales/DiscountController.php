<?php

namespace App\Http\Controllers\Sales;

use App\Models\Product;
use Illuminate\Http\Request;
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
            $products = $query->select('prod_code', 'prod_name', 'selling_price', 'discount', 'dis_per')
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
            $request->validate([
                'prod_codes' => 'required|array',
                'discount' => 'required|numeric|min:0',
                'dis_per' => 'required|numeric|min:0|max:100',
            ]);

            DB::transaction(function () use ($request) {
                // Update discounts on products
                Product::whereIn('prod_code', $request->prod_codes)->update([
                    'discount' => $request->discount,
                    'dis_per' => $request->dis_per
                ]);

                // For each affected product, refresh the corresponding Product_Upload
                // foreach ($request->prod_codes as $prodCode) {
                //     DB::statement('CALL RefreshProductUpload(?, ?)', [
                //         'Product', // p_iid
                //         $prodCode, // p_prod_code
                //     ]);
                // }
            });

            return response()->json([
                'success' => true,
                'message' => 'Discounts updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Update Discounts Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update discounts'
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
            ->select('prod_code', 'prod_name', 'selling_price', 'discount', 'dis_per')
            ->orderBy('prod_code')
            ->get();

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
