<?php

namespace App\Http\Controllers\Master;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Location;
use App\Models\DocNumber;
use App\Models\StockMaster;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use App\Models\ProductImage;
use App\Models\SubCategoryL2;
use App\Models\ProductSupplier;
use App\Models\ProductSubCategory;
use Illuminate\Support\Facades\DB;
use App\Models\ProductSubCategoryL2;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Master\MagazineRequest;
use App\Http\Resources\Master\MagazineResource;

class MagazineController extends Controller
{
    public function generateMagazineCode()
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

    public function index(Request $request)
    {
        try {
            $userLocation = $request->user()->location ?? null;

            $products = Product::where('status', 1)
                ->where('department', '15')
                ->with(['category', 'subCategories', 'subCategoryL2s', 'department', 'publisher', 'suppliers', 'images', 'languageRelation', 'unit'])
                ->get();

            if ($userLocation) {
                // Get stock sum per product for the user's location
                $stocks = StockMaster::whereIn('prod_code', $products->pluck('prod_code'))
                    ->where('location', $userLocation)
                    ->where('iid', '!=', 'CREATE')
                    ->groupBy('prod_code')
                    ->select('prod_code', DB::raw('SUM(qty) as total_qty'))
                    ->pluck('total_qty', 'prod_code');

                foreach ($products as $product) {
                    $product->current_stock = (float) ($stocks[$product->prod_code] ?? 0);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Magazines fetched successfully',
                'data' => MagazineResource::collection($products)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch magazines',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($prod_code)
    {
        try {
            $product = Product::where('prod_code', $prod_code)
                ->with(['subCategories.category.department', 'subCategoryL2s', 'department.categories', 'publisher', 'suppliers', 'images', 'languageRelation'])
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Magazine not found',
                    'error' => 'The requested magazine does not exist.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Magazine fetched successfully',
                'data' => new MagazineResource($product)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Magazine not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(MagazineRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Check if product code already exists, if so, increment until unique
            while (Product::where('prod_code', $data['prod_code'])->exists()) {
                $data['prod_code']++;
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

            // Handle sub_categories data
            $subCategoryCodes = [];
            if ($request->has('sub_category') && !empty($request->input('sub_category'))) {
                $subCategoryCodes = explode(',', $request->input('sub_category'));
                // Validate that all sub_category codes exist
                $existingSubCategories = SubCategory::whereIn('scat_code', $subCategoryCodes)->pluck('scat_code')->toArray();
                $nonExistingSubCategories = array_diff($subCategoryCodes, $existingSubCategories);

                if (!empty($nonExistingSubCategories)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Some sub categories do not exist: ' . implode(', ', $nonExistingSubCategories)
                    ], 422);
                }
            }

            // Handle sub_category_l2 data
            $subCategoryL2Codes = [];
            if ($request->has('sub_category_l2') && !empty($request->input('sub_category_l2'))) {
                $subCategoryL2Codes = explode(',', $request->input('sub_category_l2'));
            }

            // Separate image data from product data
            unset($data['images']);

            // Handle product image upload
            if ($request->hasFile('prod_image')) {
                $prodImage = $request->file('prod_image');
                $filename = $data['prod_code'] . '.' . $prodImage->getClientOriginalExtension();
                $prodImagePath = $prodImage->storeAs('products/main', $filename, 's3');
                $data['prod_image'] = $prodImagePath;
            } else {
                $data['prod_image'] = $data['prod_code'];
            }

            unset($data['supplier']);
            unset($data['sub_category']);
            unset($data['sub_category_l2']);

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

            // Handle sub_categories data
            if (!empty($subCategoryCodes)) {
                foreach ($subCategoryCodes as $subCategoryCode) {
                    $subCategory = SubCategory::where('scat_code', $subCategoryCode)->first();
                    if ($subCategory) {
                        ProductSubCategory::create([
                            'prod_code' => $product->prod_code,
                            'sub_category_id' => $subCategory->id,
                            'created_by' => auth()->id()
                        ]);
                    }
                }
            }

            // Handle sub_category_l2 data
            if (!empty($subCategoryL2Codes)) {
                foreach ($subCategoryL2Codes as $l2Code) {
                    $subCategoryL2 = SubCategoryL2::where('scat_l2_code', $l2Code)->first();
                    if ($subCategoryL2) {
                        ProductSubCategoryL2::create([
                            'prod_code' => $product->prod_code,
                            'sub_category_l2_id' => $subCategoryL2->id,
                            'created_by' => auth()->id()
                        ]);
                    }
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
            $product->load(['department', 'category', 'subCategories', 'subCategoryL2s', 'publisher', 'suppliers', 'images', 'languageRelation']);

            return response()->json([
                'success' => true,
                'message' => 'Magazine created successfully',
                'data' => new MagazineResource($product)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create magazine',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(MagazineRequest $request, $prod_code)
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

            // Handle sub_categories data
            $subCategoryCodes = [];
            if ($request->has('sub_category') && !empty($request->input('sub_category'))) {
                $subCategoryCodes = explode(',', $request->input('sub_category'));
                // Validate that all sub_category codes exist
                $existingSubCategories = SubCategory::whereIn('scat_code', $subCategoryCodes)->pluck('scat_code')->toArray();
                $nonExistingSubCategories = array_diff($subCategoryCodes, $existingSubCategories);

                if (!empty($nonExistingSubCategories)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Some sub categories do not exist: ' . implode(', ', $nonExistingSubCategories)
                    ], 422);
                }
            }

            // Handle sub_category_l2 data
            $subCategoryL2Codes = [];
            if ($request->has('sub_category_l2') && !empty($request->input('sub_category_l2'))) {
                $subCategoryL2Codes = explode(',', $request->input('sub_category_l2'));
            }

            // Separate image data from product data
            unset($data['images']);

            // Handle product image update
            if ($request->hasFile('prod_image')) {
                // Delete old image if exists
                if ($product->prod_image) {
                    Storage::disk('s3')->delete($product->prod_image);
                }
                $prodImage = $request->file('prod_image');
                $filename = $new_prod_code . '.' . $prodImage->getClientOriginalExtension();
                $data['prod_image'] = $prodImage->storeAs('products/main', $filename, 's3');
            } else {
                unset($data['prod_image']);
            }

            unset($data['supplier']);
            unset($data['sub_category']);
            unset($data['sub_category_l2']);
            if (isset($data['prod_code'])) {
                $data['barcode'] = $data['prod_code'];
            }
            $product->update($data);

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

            // Sync sub_categories - delete existing and create new rows
            if ($subCategoryCodes !== null) {
                // Delete existing sub category relationships
                ProductSubCategory::where('prod_code', $product->prod_code)->delete();

                // Create new sub category relationships
                foreach ($subCategoryCodes as $subCategoryCode) {
                    $subCategory = SubCategory::where('scat_code', $subCategoryCode)->first();
                    if ($subCategory) {
                        ProductSubCategory::create([
                            'prod_code' => $product->prod_code,
                            'sub_category_id' => $subCategory->id,
                            'created_by' => auth()->id(),
                            'updated_by' => auth()->id()
                        ]);
                    }
                }
            }

            // Sync sub_category_l2 - delete existing and create new rows
            if (!empty($subCategoryL2Codes)) {
                ProductSubCategoryL2::where('prod_code', $product->prod_code)->delete();

                foreach ($subCategoryL2Codes as $l2Code) {
                    $subCategoryL2 = SubCategoryL2::where('scat_l2_code', $l2Code)->first();
                    if ($subCategoryL2) {
                        ProductSubCategoryL2::create([
                            'prod_code' => $product->prod_code,
                            'sub_category_l2_id' => $subCategoryL2->id,
                            'created_by' => auth()->id()
                        ]);
                    }
                }
            }

            DB::commit();

            // Load relationships for the resource
            $product->load(['department', 'category', 'subCategories', 'subCategoryL2s', 'publisher', 'suppliers', 'images', 'languageRelation']);

            return response()->json([
                'success' => true,
                'message' => 'Magazine updated successfully',
                'data' => new MagazineResource($product)
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update magazine',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
