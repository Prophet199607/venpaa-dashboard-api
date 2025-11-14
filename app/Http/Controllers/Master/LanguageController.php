<?php

namespace App\Http\Controllers\Master;

use App\Models\Language;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LanguageController extends Controller
{
    public function index()
    {
        try {
            $languages = Language::all();
            return response()->json([
                'success' => true,
                'message' => 'Languages fetched successfully',
                'data' => $languages
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch languages',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
