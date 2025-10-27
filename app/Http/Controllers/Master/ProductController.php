<?php

namespace App\Http\Controllers\Master;

use App\Models\Product;
use App\Models\DocNumber;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Master\ProductRequest;
use App\Http\Resources\Master\ProductResource;

class ProductController extends Controller
{
    public function generateProductCode()
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
                ->where('department', '!=', '10')
                ->with(['category', 'subCategory', 'department', 'supplierDetails', 'images'])
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Products fetched successfully',
                'data' => ProductResource::collection($products)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($prod_code)
    {
        try {
            $product = Product::where('prod_code', $prod_code)
                ->with(['subCategory.category.department', 'supplierDetails', 'images'])
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                    'error' => 'The requested product does not exist.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product fetched successfully',
                'data' => new ProductResource($product->load('images'))
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(ProductRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Check if Product code already exists
            if (Product::where('prod_code', $data['prod_code'])->exists()) {
                $docCode = DocNumber::where('type', 'Product')->first()->getDocCode();
                $data['prod_code'] = $docCode['code'];
            }

            // Separate image data from product data
            $images = $request->file('images');
            unset($data['images']);

            // Handle Product image upload
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
            $product->load(['department', 'category', 'subCategory', 'supplierDetails', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => new ProductResource($product)
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

    public function update(ProductRequest $request, $prod_code)
    {
        try {
            DB::beginTransaction();
            $product = Product::where('prod_code', $prod_code)->first();
            $data = $request->validated();
            $data['updated_by'] = auth()->id();

            $new_prod_code = $data['prod_code'] ?? $prod_code;

            // Separate image data from product data
            $images = $request->file('images');
            unset($data['images']);

            // Handle product image update
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
            $product->load(['department', 'category', 'subCategory', 'supplierDetails', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => new ProductResource($product)
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

    public function search(Request $request)
    {
        try {
            $searchTerm = $request->search;
            $supplier = $request->supplier;

            if (empty($searchTerm)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Products fetched successfully',
                    'data' => [],
                ], 200);
            }

            $query = Product::where('status', 1);

            if ($supplier) {
                $query->where('supplier', $supplier);
            }

            $products = $query->where(function ($query) use ($searchTerm) {
                    $query->where('prod_code', 'LIKE', '%' . $searchTerm . '%')
                        ->orWhere('prod_name', 'LIKE', '%' . $searchTerm . '%')
                        ->orWhere('isbn', 'LIKE', '%' . $searchTerm . '%')
                        ->orWhere('barcode', 'LIKE', '%' . $searchTerm . '%');
                })
                ->limit(100)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Products fetched successfully',
                'data' => ProductResource::collection($products)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
