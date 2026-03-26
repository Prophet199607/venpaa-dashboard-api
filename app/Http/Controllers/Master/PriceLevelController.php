<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\PriceLevel;
use Illuminate\Http\Request;
use App\Models\PriceLevelLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PriceLevelController extends Controller
{
    public function index(Request $request)
    {
        $query = PriceLevel::query();

        if ($request->filled('prod_code')) {
            $query->where('prod_code', $request->prod_code);
        }

        return response()->json([
            'success' => true,
            'data' => $query->with('product')->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'prod_code' => 'required|string',
            'purchase_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'original_selling_price' => 'nullable|numeric',
            'wholesale_price' => 'required|numeric',
            'has_expiry' => 'boolean',
            'expiry_date' => 'nullable|date',
        ]);

        $validated['original_selling_price'] = $validated['original_selling_price'] ?? 0;

        $priceLevel = PriceLevel::create($validated);

        $this->logAction($priceLevel, 'created');

        return response()->json([
            'success' => true,
            'message' => 'Price level created successfully',
            'data' => $priceLevel
        ]);
    }

    public function update(Request $request, $id)
    {
        $priceLevel = PriceLevel::findOrFail($id);

        $validated = $request->validate([
            'purchase_price' => 'sometimes|required|numeric',
            'selling_price' => 'sometimes|required|numeric',
            'original_selling_price' => 'nullable|numeric',
            'wholesale_price' => 'sometimes|required|numeric',
            'has_expiry' => 'sometimes|boolean',
            'expiry_date' => 'nullable|date',
        ]);

        if (array_key_exists('original_selling_price', $validated) && is_null($validated['original_selling_price'])) {
            $validated['original_selling_price'] = 0;
        }

        if (isset($validated['has_expiry']) && !$validated['has_expiry']) {
            $validated['expiry_date'] = null;
        }

        $priceLevel->update($validated);

        $this->logAction($priceLevel->fresh(), 'updated');

        return response()->json([
            'success' => true,
            'message' => 'Price level updated successfully',
            'data' => $priceLevel
        ]);
    }

    public function destroy($id)
    {
        $priceLevel = PriceLevel::findOrFail($id);
        $this->logAction($priceLevel, 'deleted');
        $priceLevel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Price level deleted successfully'
        ]);
    }

    public function deleteAll(Request $request)
    {
        $prod_code = $request->query('prod_code');
        $query = PriceLevel::query();

        if ($prod_code) {
            $query->where('prod_code', $prod_code);
        }

        $priceLevels = $query->get();
        foreach ($priceLevels as $pl) {
            $this->logAction($pl, 'deleted');
        }

        $query->delete();

        return response()->json([
            'success' => true,
            'message' => $prod_code ? "All price levels for product $prod_code deleted" : "All price levels deleted successfully"
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
                'price_levels.*.original_selling_price' => 'nullable|numeric|min:0',
                'price_levels.*.wholesale_price' => 'required|numeric|min:0',
                'price_levels.*.has_expiry' => 'sometimes|boolean',
                'price_levels.*.expiry_date' => 'nullable|date',
            ]);

            $prod_code = $validated['prod_code'];
            $savedCount = 0;

            foreach ($validated['price_levels'] as $level) {
                $hasExpiry = isset($level['has_expiry']) ? (bool)$level['has_expiry'] : false;
                $expiryDate = $hasExpiry ? ($level['expiry_date'] ?? null) : null;

                $priceLevel = PriceLevel::create([
                    'prod_code' => $level['prod_code'] ?? $prod_code,
                    'purchase_price' => $level['purchase_price'],
                    'selling_price' => $level['selling_price'],
                    'original_selling_price' => $level['original_selling_price'] ?? 0,
                    'wholesale_price' => $level['wholesale_price'],
                    'has_expiry' => $hasExpiry,
                    'expiry_date' => $expiryDate,
                ]);

                $this->logAction($priceLevel, 'created');

                $savedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "{$savedCount} price level(s) saved successfully",
                'count' => $savedCount
            ]);
        } catch (ValidationException $e) {
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

    private function logAction($priceLevel, $action)
    {
        PriceLevelLog::create([
            'action'          => $action,
            'price_level_id'  => $priceLevel->id,
            'u_id'            => $priceLevel->u_id,
            'prod_code'       => $priceLevel->prod_code,
            'purchase_price'  => $priceLevel->purchase_price,
            'selling_price'   => $priceLevel->selling_price,
            'original_selling_price' => $priceLevel->original_selling_price,
            'wholesale_price' => $priceLevel->wholesale_price,
            'modified_user'   => $priceLevel->modified_user,
            'has_expiry'      => $priceLevel->has_expiry,
            'expiry_date'     => $priceLevel->expiry_date,
        ]);
    }
}
