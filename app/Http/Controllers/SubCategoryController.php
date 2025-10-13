<?php

namespace App\Http\Controllers;

use App\Models\DocNumber;
use App\Models\SubCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubCategoryRequest;
use App\Http\Resources\SubCategoryResource;

class SubCategoryController extends Controller
{
    public function generateSubCategoryCode()
    {
        try {
            $doc = DocNumber::where('type', 'SubCategory')->first();

            if (!$doc) {
                // If no SubCategory document exists, create one
                $doc = DocNumber::create([
                    'type' => 'SubCategory',
                    'prefix' => 'SC',
                    'last_id' => 0
                ]);
            }

            $nextId = $doc->last_id + 1;
            $number = $doc->prefix . str_pad($nextId, 3, '0', STR_PAD_LEFT);

            return response()->json([
                'success' => true,
                'message' => 'Code generated successfully',
                'code' => $number
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
            $subCategories = SubCategory::with(['category', 'department'])->get();

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
            $subCategory = SubCategory::with(['category.department'])->where('scat_code', $scat_code)->firstOrFail();

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

    public function store(SubCategoryRequest $request)
    {
        try {
            $data = $request->validated();

            // Check if SubCategory code already exists
            if (SubCategory::where('scat_code', $data['scat_code'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'SubCategory code already exists'
                ], 422);
            }

            $data['created_by'] = auth()->id();
            $subCategory = SubCategory::create($data);

            $doc = DocNumber::where('type', 'SubCategory')->first();
            if ($doc) {
                $doc->last_id += 1;
                $doc->save();
            }

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
            $subCategory = SubCategory::where('scat_code', $scat_code)->firstOrFail();
            $data = $request->validated();
            $data['updated_by'] = auth()->id();
            $subCategory->update($data);

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