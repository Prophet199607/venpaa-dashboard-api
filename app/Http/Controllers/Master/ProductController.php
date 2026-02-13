<?php

namespace App\Http\Controllers\Master;

use App\Models\Unit;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Location;
use App\Models\DocNumber;
use App\Models\StockMaster;
use Illuminate\Http\Request;
use App\Models\ProductImage;
use App\Models\ProductSupplier;
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
                ->with(['category', 'subCategory', 'department', 'suppliers', 'images'])
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
                ->with(['subCategory.category.department', 'suppliers', 'images'])
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

            // Handle suppliers data
            $supplierCodes = [];
            if ($request->has('supplier') && !empty($request->input('supplier'))) {
                $supplierCodes = explode(',', $request->input('supplier'));
                // Validate that all supplier codes exist
                $existingSuppliers = Supplier::whereIn('sup_code', $supplierCodes)->pluck('sup_code')->toArray();
                $nonExistingSuppliers = array_diff($supplierCodes, $existingSuppliers);

                if (!empty($nonExistingSuppliers)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Some suppliers do not exist: ' . implode(', ', $nonExistingSuppliers)
                    ], 422);
                }
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
            }

            unset($data['supplier']);
            $data['barcode'] = $data['prod_code'];
            $product = Product::create($data);

            // Handle suppliers data
            if (!empty($supplierCodes)) {
                foreach ($supplierCodes as $supplierCode) {
                    $supplier = Supplier::where('sup_code', $supplierCode)->first();
                    if ($supplier) {
                        ProductSupplier::create([
                            'prod_code' => $product->prod_code,
                            'supplier_id' => $supplier->id,
                            'created_by' => auth()->id()
                        ]);
                    }
                }
            }

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

            // Create stock master records for all active locations
            $activeLocations = Location::where('is_active', 1)->get();
            foreach($activeLocations as $location) {
                StockMaster::create([
                    'location' => $location->loca_code,
                    'transaction_date' => '',
                    'doc_no' => '',
                    'prod_code' => $product->prod_code,
                    'iid' => 'CREATE',
                    'qty' => 0.000,
                    'purchase_price' => $data['purchase_price'] ?? 0.00,
                    'selling_price' => $data['selling_price'] ?? 0.00,
                    'amount' => 0.00,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            // Load relationships for the resource
            $product->load(['department', 'category', 'subCategory', 'suppliers', 'images']);

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

            // Handle suppliers data
            $supplierCodes = [];
            if ($request->has('supplier') && !empty($request->input('supplier'))) {
                $supplierCodes = explode(',', $request->input('supplier'));
                // Validate that all supplier codes exist
                $existingSuppliers = Supplier::whereIn('sup_code', $supplierCodes)->pluck('sup_code')->toArray();
                $nonExistingSuppliers = array_diff($supplierCodes, $existingSuppliers);

                if (!empty($nonExistingSuppliers)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Some suppliers do not exist: ' . implode(', ', $nonExistingSuppliers)
                    ], 422);
                }
            }

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

            unset($data['supplier']);
            $product->update($data);
            DB::commit();

            // Sync suppliers - delete existing and create new rows
            if ($supplierCodes !== null) {
                // Delete existing supplier relationships
                ProductSupplier::where('prod_code', $product->prod_code)->delete();

                // Create new supplier relationships
                foreach ($supplierCodes as $supplierCode) {
                    $supplier = Supplier::where('sup_code', $supplierCode)->first();
                    if ($supplier) {
                        ProductSupplier::create([
                            'prod_code' => $product->prod_code,
                            'supplier_id' => $supplier->id,
                            'created_by' => auth()->id(),
                            'updated_by' => auth()->id()
                        ]);
                    }
                }
            }

            DB::commit();

            // Load relationships for the resource
            $product->load(['department', 'category', 'subCategory', 'suppliers', 'images']);

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

            $query = Product::where('status', 1)->with('unit');

            if ($supplier) {
                $query->whereHas('suppliers', function ($q) use ($supplier) {
                    $q->where('sup_code', $supplier);
                });
            }

            $products = $query->where(function ($query) use ($searchTerm) {
                $query->where('prod_code', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('prod_name', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('isbn', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('barcode', 'LIKE', '%' . $searchTerm . '%');
            })
             ->orderByRaw("CASE 
                WHEN prod_code = ? THEN 1 
                WHEN barcode = ? THEN 1
                WHEN isbn = ? THEN 1
                WHEN prod_name = ? THEN 2
                ELSE 3 
            END", [$searchTerm, $searchTerm, $searchTerm, $searchTerm])
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

    public function searchBasic (Request $request)
    {
        try {
            $searchTerm = $request->search;

            if (empty($searchTerm)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Products fetched successfully',
                    'data' => [],
                ], 200);
            }

            $query = Product::where('status', 1)->with('unit');

            $products = $query->where(function ($query) use ($searchTerm) {
                $query->where('prod_code', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('prod_name', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('isbn', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('barcode', 'LIKE', '%' . $searchTerm . '%');
            })
            ->orderByRaw("CASE 
                WHEN prod_code = ? THEN 1 
                WHEN barcode = ? THEN 1
                WHEN isbn = ? THEN 1
                WHEN prod_name = ? THEN 2
                ELSE 3 
            END", [$searchTerm, $searchTerm, $searchTerm, $searchTerm])
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

    public function unitTypes()
    {
        try {
            $units = Unit::all();

            return response()->json([
                'success' => true,
                'message' => 'Unit types fetched successfully',
                'data' => $units,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unit types',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
