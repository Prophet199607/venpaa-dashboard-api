<?php

namespace App\Http\Controllers\Transaction;

use App\Models\DiscardType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;


class ProductDiscardController extends Controller
{
    public function getDiscardTypes()
    {
        try {
            $types = DiscardType::all();
            return response()->json([
                'success' => true,
                'data' => $types
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch discard types: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getUnsavedSessions()
    {
        // Placeholder for now as it's called by the frontend
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }
}
