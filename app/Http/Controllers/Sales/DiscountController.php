<?php

namespace App\Http\Controllers\Sales;

use Carbon\Carbon;
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
            $request->validate([
                'prod_codes' => 'required|array',
                'discount' => 'required|numeric|min:0',
                'dis_per' => 'required|numeric|min:0|max:100',
                'dis_start_date' => 'nullable|string',
                'dis_end_date' => 'nullable|string',
            ]);

            $startDate = null;
            $endDate = null;

            if ($request->filled('dis_start_date')) {
                try {
                    $startDate = Carbon::createFromFormat('d/m/y', $request->dis_start_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    $startDate = Carbon::parse($request->dis_start_date)->format('Y-m-d');
                }
            }

            if ($request->filled('dis_end_date')) {
                try {
                    $endDate = Carbon::createFromFormat('d/m/y', $request->dis_end_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    $endDate = Carbon::parse($request->dis_end_date)->format('Y-m-d');
                }
            }

            DB::transaction(function () use ($request, $startDate, $endDate) {
                // If discount is being removed, clear dates too
                $updateData = [
                    'discount' => $request->discount,
                    'dis_per' => $request->dis_per,
                    'dis_start_date' => ($request->discount == 0 && $request->dis_per == 0) ? null : $startDate,
                    'dis_end_date' => ($request->discount == 0 && $request->dis_per == 0) ? null : $endDate,
                ];

                Product::whereIn('prod_code', $request->prod_codes)->update($updateData);
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
            ->get()
            ->map(function($product) {
                // Format dates for display as DD/MM/YY as requested
                $product->dis_start_date = $product->dis_start_date ? Carbon::parse($product->dis_start_date)->format('d/m/y') : null;
                $product->dis_end_date = $product->dis_end_date ? Carbon::parse($product->dis_end_date)->format('d/m/y') : null;
                return $product;
            });

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
