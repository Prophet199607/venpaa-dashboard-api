<?php

namespace App\Http\Controllers;

use App\Models\DocNumber;
use App\Models\Category;
use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\CategoryResource;

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
            $categories = Category::with('subCategories')->where('status', 1)->get();
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
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Handle image upload
            if ($request->hasFile('cat_image')) {
                $imagePath = $request->file('cat_image')->store('categories', 'public');
                $data['cat_image'] = $imagePath;
            }

            // Check if category code already exists
            if (Category::where('cat_code', $data['cat_code'])->exists()) {
                $data['cat_code'] = DocNumber::where('type', 'Category')->first()->getDocCode();
            }

            $category = Category::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => new CategoryResource($category)
            ], 201);
        } catch (\Exception $e) {
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
            $category = Category::where('cat_code', $cat_code)->first();
            $data = $request->validated();

            // Handle image update if provided
            if ($request->hasFile('cat_image')) {
                // Delete old image if exists
                if ($category->cat_image) {
                    Storage::disk('public')->delete($category->cat_image);
                }

                $imagePath = $request->file('cat_image')->store('categories', 'public');
                $data['cat_image'] = $imagePath;
            }

            $data['updated_by'] = auth()->id();
            $category->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => new CategoryResource($category)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}