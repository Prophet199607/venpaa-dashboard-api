<?php

namespace App\Http\Controllers\Master;

use App\Models\DocNumber;
use App\Models\SubCategoryL2;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\SubCategoryL2Request;
use App\Http\Resources\Master\SubCategoryL2Resource;

class SubCategoryL2Controller extends Controller
{
    public function generateSubCategoryL2Code()
    {
        try {
            $docCode = DocNumber::where('type', 'SubCategoryL2')->first()->getDocCode();

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
            $subCategoriesL2 = SubCategoryL2::with(['subCategory', 'category', 'department_relation'])->where('status', 1)->get();

            return response()->json([
                'success' => true,
                'message' => 'Sub categories L2 fetched successfully',
                'data' => SubCategoryL2Resource::collection($subCategoriesL2)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sub categories L2',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($scat_l2_code)
    {
        try {
            $subCategoryL2 = SubCategoryL2::with(['subCategory', 'category', 'department_relation'])->where('scat_l2_code', $scat_l2_code)->first();

            return response()->json([
                'success' => true,
                'message' => 'Sub category L2 fetched successfully',
                'data' => new SubCategoryL2Resource($subCategoryL2)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sub category L2 not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(SubCategoryL2Request $request)
    {
        try {
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Check if sub category L2 code already exists
            if (SubCategoryL2::where('scat_l2_code', $data['scat_l2_code'])->exists()) {
                $data['scat_l2_code'] = DocNumber::where('type', 'SubCategoryL2')->first()->getDocCode();
            }

            $subCategoryL2 = SubCategoryL2::create($data);

            return response()->json([
                'success' => true,
                'message' => 'SubCategoryL2 created successfully',
                'data' => new SubCategoryL2Resource($subCategoryL2)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create SubCategoryL2',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(SubCategoryL2Request $request, $scat_l2_code)
    {
        try {
            $subCategoryL2 = SubCategoryL2::where('scat_l2_code', $scat_l2_code)->first();
            $data = $request->validated();
            $data['updated_by'] = auth()->id();
            $subCategoryL2->update($data);

            return response()->json([
                'success' => true,
                'message' => 'SubCategoryL2 updated successfully',
                'data' => new SubCategoryL2Resource($subCategoryL2)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update SubCategoryL2',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
