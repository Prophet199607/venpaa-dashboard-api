<?php

namespace App\Http\Controllers\Master;

use App\Models\DocNumber;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\SubCategoryRequest;
use App\Http\Resources\Master\SubCategoryResource;

class SubCategoryController extends Controller
{
    public function generateSubCategoryCode()
    {
        try {
            $docCode = DocNumber::where('type', 'SubCategory')->first()->getDocCode();

            return response()->json([
                'success' => true,
                'message' => 'Code generated successfully',
                'code' => $docCode['code']
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        try {
            $subCategories = SubCategory::with(['category', 'department'])->where('status', 1)->get();

            return response()->json([
                'success' => true,
                'message' => 'Sub categories fetched successfully',
                'data' => SubCategoryResource::collection($subCategories)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sub categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($scat_code)
    {
        try {
            $subCategory = SubCategory::with(['category.department', 'department'])->where('scat_code', $scat_code)->first();

            return response()->json([
                'success' => true,
                'message' => 'Sub category fetched successfully',
                'data' => new SubCategoryResource($subCategory)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sub category not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function search(Request $request)
    {
        try {
            $query = $request->input('query', '');
            $cat_code = $request->input('cat_code');

            $subCategories = SubCategory::with(['category', 'department'])->where('status', 1)
                ->when($cat_code, function ($q) use ($cat_code) {
                    $q->where('cat_code', $cat_code);
                })
                ->where(function ($q) use ($query) {
                    $q->where('scat_name', 'LIKE', "%{$query}%")
                    ->orWhere('scat_code', 'LIKE', "%{$query}%");
                })
                ->limit(100)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Sub categories search results',
                'data' => SubCategoryResource::collection($subCategories),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search sub categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(SubCategoryRequest $request)
    {
        try {
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Check if sub category code already exists
            if (SubCategory::where('scat_code', $data['scat_code'])->exists()) {
                $docCode = DocNumber::where('type', 'SubCategory')->first()->getDocCode();
                $data['scat_code'] = $docCode['code'];
            }

            $subCategory = SubCategory::create($data)->load(['category', 'department']);

            return response()->json([
                'success' => true,
                'message' => 'SubCategory created successfully',
                'data' => new SubCategoryResource($subCategory)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create SubCategory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(SubCategoryRequest $request, $scat_code)
    {
        try {
            $subCategory = SubCategory::where('scat_code', $scat_code)->first();
            $data = $request->validated();
            $data['updated_by'] = auth()->id();
            $subCategory->update($data);
            $subCategory->load(['category', 'department']);

            return response()->json([
                'success' => true,
                'message' => 'SubCategory updated successfully',
                'data' => new SubCategoryResource($subCategory)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update SubCategory',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
