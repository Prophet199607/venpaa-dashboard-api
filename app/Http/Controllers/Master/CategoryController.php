<?php

namespace App\Http\Controllers\Master;

use App\Models\DocNumber;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Master\CategoryRequest;
use App\Http\Resources\Master\CategoryResource;
use App\Http\Resources\Master\SubCategoryResource;

class CategoryController extends Controller
{
    public function generateCategoryCode()
    {
        try {
            $docCode = DocNumber::where('type', 'Category')->first()->getDocCode();

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
            $categories = Category::with(['subCategories', 'department'])->where('status', 1)->get();
            return response()->json([
                'success' => true,
                'message' => 'Categories fetched successfully',
                'data' => CategoryResource::collection($categories)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($cat_code)
    {
        try {
            $category = Category::where('cat_code', $cat_code)->first();
            return response()->json([
                'success' => true,
                'message' => 'Category fetched successfully',
                'data' => new CategoryResource($category)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(CategoryRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Check if category code already exists
            if (Category::where('cat_code', $data['cat_code'])->exists()) {
                $docCode = DocNumber::where('type', 'Category')->first()->getDocCode();
                $data['cat_code'] = $docCode['code'];
            }

            // Handle image upload
            if ($request->hasFile('cat_image')) {
                $image = $request->file('cat_image');
                $filename = $data['cat_code'] . '.' . $image->getClientOriginalExtension();
                // $data['cat_image'] = $image->storeAs('categories', $filename, 'public');
                $data['cat_image'] = $image->storeAs('categories', $filename, 's3');
            } else {
                $data['cat_image'] = $data['cat_code'];
            }

            $category = Category::create($data);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => new CategoryResource($category)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(CategoryRequest $request, $cat_code)
    {
        try {
            DB::beginTransaction();
            $category = Category::where('cat_code', $cat_code)->first();
            $data = $request->validated();
            $data['updated_by'] = auth()->id();

            $new_cat_code = $data['cat_code'] ?? $cat_code;

            // If cat_code is changing, or if a new image is uploaded, the old image is invalid.
            if ((isset($data['cat_code']) && $data['cat_code'] !== $cat_code) || $request->hasFile('cat_image')) {
                if ($category->cat_image) {
                    // Storage::disk('public')->delete($category->cat_image);
                    Storage::disk('s3')->delete($category->cat_image);
                }
            }

            if ($request->hasFile('cat_image')) {
                $image = $request->file('cat_image');
                $filename = $new_cat_code . '.' . $image->getClientOriginalExtension();
                // $data['cat_image'] = $image->storeAs('categories', $filename, 'public');
                $data['cat_image'] = $image->storeAs('categories', $filename, 's3');
            } else {
                unset($data['cat_image']);
            }

            $category->update($data);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => new CategoryResource($category)
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function subCategories($cat_code)
    {
        try {
            $category = Category::where('cat_code', $cat_code)->firstOrFail();
            $subCategories = $category->subCategories()->with(['department', 'category'])->get();

            return response()->json([
                'success' => true,
                'message' => 'Sub-categories fetched successfully',
                'data' => SubCategoryResource::collection($subCategories)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sub-categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
