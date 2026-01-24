<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\PriceLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PriceLevelController extends Controller
{
    public function index(Request $request)
    {
        $query = PriceLevel::query();

        if ($request->has('prod_code')) {
            $query->where('prod_code', $request->prod_code);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'prod_code' => 'required|string',
            'purchase_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'wholesale_price' => 'required|numeric',
            'has_expiry' => 'boolean',
            'expiry_date' => 'nullable|date',
        ]);

        $priceLevel = PriceLevel::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Price level created successfully',
            'data' => $priceLevel
        ]);
    }

    public function destroy($id)
    {
        $priceLevel = PriceLevel::findOrFail($id);
        $priceLevel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Price level deleted successfully'
        ]);
    }

    public function deleteByProduct($prod_code)
    {
        PriceLevel::where('prod_code', $prod_code)->delete();

        return response()->json([
            'success' => true,
            'message' => 'All price levels for this product deleted'
        ]);
    }

    public function deleteExpired()
    {
        PriceLevel::where('has_expiry', true)
            ->where('expiry_date', '<', now()->toDateString())
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'All expired price levels removed'
        ]);
    }

    public function batchStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'prod_code' => 'required|string',
                'price_levels' => 'required|array|min:1',
                'price_levels.*.purchase_price' => 'required|numeric|min:0',
                'price_levels.*.selling_price' => 'required|numeric|min:0',
                'price_levels.*.wholesale_price' => 'required|numeric|min:0',
                'price_levels.*.has_expiry' => 'sometimes|boolean',
                'price_levels.*.expiry_date' => 'nullable|date',
            ]);

            $prod_code = $validated['prod_code'];
            $savedCount = 0;

            foreach ($validated['price_levels'] as $level) {
                // Ensure has_expiry defaults to false if not provided
                $hasExpiry = isset($level['has_expiry']) ? (bool)$level['has_expiry'] : false;

                // If has_expiry is false, set expiry_date to null
                $expiryDate = $hasExpiry ? ($level['expiry_date'] ?? null) : null;

                PriceLevel::create([
                    'prod_code' => $prod_code,
                    'purchase_price' => $level['purchase_price'],
                    'selling_price' => $level['selling_price'],
                    'wholesale_price' => $level['wholesale_price'],
                    'has_expiry' => $hasExpiry,
                    'expiry_date' => $expiryDate,
                ]);

                $savedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "{$savedCount} price level(s) saved successfully",
                'count' => $savedCount
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('PriceLevel batchStore error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save price levels: ' . $e->getMessage()
            ], 500);
        }
    }
}
