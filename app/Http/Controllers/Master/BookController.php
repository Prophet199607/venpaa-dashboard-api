<?php

namespace App\Http\Controllers\Master;

use App\Models\Product;
use App\Models\DocNumber;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Master\BookRequest;
use App\Http\Resources\Master\BookResource;

class BookController extends Controller
{
    public function generateBookCode()
    {
        try {
            $docCode = DocNumber::where('type', 'Product')->first()->getDocCode();

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
            $products = Product::where('status', 1)
                ->where('department', '10')
                ->with(['authorDetails', 'category', 'subCategory', 'department', 'bookType', 'publisher', 'supplierDetails', 'images'])
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Books fetched successfully',
                'data' => BookResource::collection($products)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch books',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($prod_code)
    {
        try {
            $product = Product::where('prod_code', $prod_code)
                ->with(['authorDetails', 'subCategory.category.department', 'bookType', 'publisher', 'supplier', 'images'])
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Book not found',
                    'error' => 'The requested book does not exist.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Book fetched successfully',
                'data' => new BookResource($product->load('images'))
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(BookRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Check if Book code already exists
            if (Product::where('prod_code', $data['prod_code'])->exists()) {
                $docCode = DocNumber::where('type', 'Product')->first()->getDocCode();
                $data['prod_code'] = $docCode['code'];
            }

            // Separate image data from book data
            $images = $request->file('images');
            unset($data['images']);

            // Handle Book image upload
            if ($request->hasFile('prod_image')) {
                $prodImage = $request->file('prod_image');
                $filename = $data['prod_code'] . '.' . $prodImage->getClientOriginalExtension();
                $prodImagePath = $prodImage->storeAs('products/main', $filename, 'public');
                $data['prod_image'] = $prodImagePath;
            } else {
                $data['prod_image'] = $data['prod_code'];
            }

            $product = Product::create($data);

            // Handle multiple images upload
            if ($images) {
                foreach ($images as $image) {
                    $timestamp = now()->format('YmdHisu');
                    $filename = $product->prod_code . '-' . $timestamp . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('products/images', $filename, 'public');
                    ProductImage::create([
                        'prod_code' => $product->prod_code,
                        'image' => $imagePath,
                        'created_by' => auth()->id()
                    ]);
                }
            }

            DB::commit();

            // Load relationships for the resource
            $product->load(['bookType', 'department', 'category', 'subCategory', 'publisher', 'supplier', 'authorDetails', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Book created successfully',
                'data' => new BookResource($product)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(BookRequest $request, $prod_code)
    {
        try {
            DB::beginTransaction();
            $product = Product::where('prod_code', $prod_code)->first();
            $data = $request->validated();
            $data['updated_by'] = auth()->id();

            $new_prod_code = $data['prod_code'] ?? $prod_code;

            // Separate image data from book data
            $images = $request->file('images');
            unset($data['images']);

            // Handle book image update
            if ($request->hasFile('prod_image')) {
                // Delete old image if exists
                if ($product->prod_image) {
                    Storage::disk('public')->delete($product->prod_image);
                }
                $prodImage = $request->file('prod_image');
                $filename = $new_prod_code . '.' . $prodImage->getClientOriginalExtension();
                $data['prod_image'] = $prodImage->storeAs('products/main', $filename, 'public');
            } else {
                unset($data['prod_image']);
            }

            // Handle multiple images update
            if ($images) {
                // Delete old images
                foreach ($product->images as $image) {
                    Storage::disk('public')->delete($image->image);
                    $image->delete();
                }

                // Upload new images
                foreach ($images as $imagefile) {
                    $timestamp = now()->format('YmdHisu');
                    $filename = $new_prod_code . '-' . $timestamp . '.' . $imagefile->getClientOriginalExtension();
                    $path = $imagefile->storeAs('products/images', $filename, 'public');
                    ProductImage::create([
                        'prod_code' => $new_prod_code,
                        'image' => $path,
                        'updated_by' => auth()->id(),
                    ]);
                }
            }

            $product->update($data);
            DB::commit();

            // Load relationships for the resource
            $product->load(['bookType', 'department', 'category', 'subCategory', 'publisher', 'supplier', 'authorDetails', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Book updated successfully',
                'data' => new BookResource($product)
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}