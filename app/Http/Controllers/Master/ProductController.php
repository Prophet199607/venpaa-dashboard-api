<?php

namespace App\Http\Controllers\Master;

use App\Models\Iid;
use App\Models\Unit;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Location;
use App\Models\DocNumber;
use App\Models\StockMaster;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use App\Models\ProductImage;
use App\Models\ProductSupplier;
use App\Models\TransactionDetail;
use App\Models\TransactionHeader;
use App\Models\ProductSubCategory;
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
                ->with(['category', 'subCategories', 'department', 'suppliers', 'images'])
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
                ->with(['department.categories', 'subCategories.category.department', 'suppliers', 'images', 'languageRelation', 'unit'])
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
                'data' => new ProductResource($product)
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

            // Separate image data from product data
            $images = $request->file('images');
            unset($data['images']);

            // Handle Product image upload
            if ($request->hasFile('prod_image')) {
                $prodImage = $request->file('prod_image');
                $filename = $data['prod_code'] . '.' . $prodImage->getClientOriginalExtension();
                // $prodImagePath = $prodImage->storeAs('products/main', $filename, 'public');
                $prodImagePath = $prodImage->storeAs('products/main', $filename, 's3');
                $data['prod_image'] = $prodImagePath;
            }

            unset($data['supplier']);
            unset($data['sub_category']);
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

            // Handle multiple images upload
            if ($images) {
                foreach ($images as $image) {
                    $timestamp = now()->format('YmdHisu');
                    $filename = $product->prod_code . '-' . $timestamp . '.' . $image->getClientOriginalExtension();
                    // $imagePath = $image->storeAs('products/images', $filename, 'public');
                    $imagePath = $image->storeAs('products/images', $filename, 's3');
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
            $product->load(['department', 'category', 'subCategories', 'suppliers', 'images']);

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

            // Separate image data from product data
            $images = $request->file('images');
            unset($data['images']);

            // Handle product image update
            if ($request->hasFile('prod_image')) {
                // Delete old image if exists
                if ($product->prod_image) {
                    // Storage::disk('public')->delete($product->prod_image);
                    Storage::disk('s3')->delete($product->prod_image);
                }
                $prodImage = $request->file('prod_image');
                $filename = $new_prod_code . '.' . $prodImage->getClientOriginalExtension();
                // $data['prod_image'] = $prodImage->storeAs('products/main', $filename, 'public');
                $data['prod_image'] = $prodImage->storeAs('products/main', $filename, 's3');
            } else {
                unset($data['prod_image']);
            }

            // Handle multiple images update
            if ($images) {
                // Delete old images
                foreach ($product->images as $image) {
                    // Storage::disk('public')->delete($image->image);
                    Storage::disk('s3')->delete($image->image);
                    $image->delete();
                }

                // Upload new images
                foreach ($images as $imagefile) {
                    $timestamp = now()->format('YmdHisu');
                    $filename = $new_prod_code . '-' . $timestamp . '.' . $imagefile->getClientOriginalExtension();
                    // $path = $imagefile->storeAs('products/images', $filename, 'public');
                    $path = $imagefile->storeAs('products/images', $filename, 's3');
                    ProductImage::create([
                        'prod_code' => $new_prod_code,
                        'image' => $path,
                        'updated_by' => auth()->id(),
                    ]);
                }
            }

            unset($data['supplier']);
            unset($data['sub_category']);
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

            DB::commit();

            // Load relationships for the resource
            $product->load(['department', 'category', 'subCategories', 'suppliers', 'images']);

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
                    // $q->where('suppliers.sup_code', $supplier);
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

    // public function importOpenStock(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'file' => 'required|file|mimes:xlsx,xls,csv',
    //             'location' => 'required|string'
    //         ]);

    //         Excel::import(new OpenStockImport($request->location), $request->file('file'));

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Open stock imported successfully',
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to import open stock',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // public function exportOpenStockTemplate()
    // {
    //     return Excel::download(new OpenStockTemplateExport, 'open_stock_template.xlsx');
    // }

    public function binCard(Request $request, $prod_code)
    {
        try {
            $userLocation = $request->user()?->location;
            if ($userLocation === null || $userLocation === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Logged-in location is required to view bin card.',
                ], 400);
            }

            $location = Location::where('loca_code', $userLocation)->first();
            $locationName = $location?->loca_name ?? '';

            $product = Product::where('prod_code', $prod_code)->first();
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            $query = StockMaster::where('prod_code', $prod_code)
                ->where('location', $userLocation)
                ->where('iid', '!=', 'CREATE')
                ->orderBy('transaction_date')
                ->orderBy('id');

            $rows = $query->get();
            $iidNames = Iid::whereIn('iid', $rows->pluck('iid')->unique()->filter())->pluck('name', 'iid');

            $balance = 0;
            $transactions = [];
            foreach ($rows as $row) {
                $qty = (float) $row->qty;
                $balance += $qty;
                $stockIn = $qty > 0 ? (string) round($qty, 3) : '';
                $stockOut = $qty < 0 ? (string) round(abs($qty), 3) : '';
                $transactions[] = [
                    'transaction' => $iidNames[$row->iid] ?? $row->iid,
                    'date'        => $row->transaction_date,
                    'document'    => $row->doc_no,
                    'reference'   => $row->doc_no,
                    'cost'        => number_format((float) $row->purchase_price, 2, '.', ''),
                    'stock_in'    => $stockIn,
                    'stock_out'   => $stockOut,
                    'balance'     => (string) round($balance, 3),
                ];
            }

            $purchasePrice = $rows->isEmpty() ? 0 : (float) $rows->first()->purchase_price;
            $locationCode = $userLocation;
            $currentBalance = (string) round((float) $balance, 3);

            return response()->json([
                'success' => true,
                'data'    => [
                    'product'      => [
                        'prod_code' => $product->prod_code,
                        'prod_name' => $product->prod_name ?? '',
                    ],
                    'location'     => $locationCode,
                    'stores'       => $locationName,
                    'purchase_price' => number_format($purchasePrice, 2, '.', ''),
                    'current_balance' => $currentBalance,
                    'transactions' => $transactions,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load bin card',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function storeOpenStock(Request $request)
    {
        try {
            DB::beginTransaction();

            $items = $request->input('items', []);

            if (empty($items)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No items provided',
                ], 400);
            }

            $itemsByLocation = collect($items)->groupBy('loca_code');

            // Check if any product already has Open Stock stored in the requested locations
            foreach ($itemsByLocation as $locaCode => $locationItems) {
                foreach ($locationItems as $item) {
                    $exists = StockMaster::where('prod_code', $item['prod_code'])
                        ->where('location', $locaCode)
                        ->where('iid', 'OPS')
                        ->exists();

                    if ($exists) {
                        return response()->json([
                            'success' => false,
                            'message' => "Open stock already stored for product {$item['prod_code']} and location {$locaCode}.",
                        ], 400);
                    }
                }
            }

            $createdDocs = [];

            foreach ($itemsByLocation as $locaCode => $locationItems) {
                $generated = DocNumber::generate('OpenStock', 'OPS', 8, $locaCode);
                $docNo = is_array($generated) ? $generated['code'] : $generated;

                $netTotal = $locationItems->sum(function ($item) {
                     return (float) $item['amount'];
                });

                $header = TransactionHeader::create([
                    'doc_no' => $docNo,
                    'iid' => 'OPS',
                    'transaction_date' => now(),
                    'location' => $locaCode,
                    'remarks_ref' => 'Open Stock',
                    'created_by' => auth()->id(),
                    'subtotal' => $netTotal,
                    'net_total' => $netTotal,
                    'document_date' => now(),
                ]);

                $lineNo = 1;
                foreach ($locationItems as $item) {
                    TransactionDetail::create([
                        'line_no' => $lineNo++,
                        'doc_no' => $docNo,
                        'iid' => 'OPS',
                        'transaction_header_id' => $header->id,
                        'prod_code' => $item['prod_code'],
                        'prod_name' => $item['prod_name'],
                        'pack_size' => $item['pack_size'],
                        'pack_qty' => $item['pack_qty'],
                        'unit_qty' => $item['unit_qty'],
                        'total_qty' => $item['total_qty'],
                        'purchase_price' => $item['purchase_price'],
                        'selling_price' => $item['selling_price'],
                        'amount' => $item['amount'],
                        'created_by' => auth()->id(),
                    ]);

                    StockMaster::create([
                        'location' => $locaCode,
                        'transaction_date' => now(),
                        'doc_no' => $docNo,
                        'prod_code' => $item['prod_code'],
                        'iid' => 'OPS',
                        'qty' => $item['total_qty'],
                        'purchase_price' => $item['purchase_price'],
                        'selling_price' => $item['selling_price'],
                        'amount' => $item['amount'],
                    ]);
                }

                $createdDocs[] = $docNo;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Open stock stored successfully',
                'docs' => $createdDocs
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to store open stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkOpenStock($prod_code)
    {
        try {
            $existingLocations = StockMaster::where('prod_code', $prod_code)
                ->where('iid', 'OPS')
                ->pluck('location')
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'existing_locations' => $existingLocations
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check open stock status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function stockByLocation($prod_code)
    {
        try {
            $locations = Location::where('is_active', 1)->get()->keyBy('loca_code');

            $stocks = StockMaster::select('location', DB::raw('SUM(qty) as total_qty'))
                ->where('prod_code', $prod_code)
                ->where('iid', '!=', 'CREATE')
                ->groupBy('location')
                ->get();

            $data = $locations->map(function ($loc) use ($stocks, $prod_code) {
                $stock = $stocks->firstWhere('location', $loc->loca_code);
                
                $latestStock = StockMaster::where('prod_code', $prod_code)
                    ->where('location', $loc->loca_code)
                    ->where('iid', '!=', 'CREATE')
                    ->orderByDesc('id')
                    ->first();
                
                return [
                    'loca_code' => $loc->loca_code,
                    'loca_name' => $loc->loca_name,
                    'qty' => $stock ? $stock->total_qty : 0,
                    'selling_price' => $latestStock ? $latestStock->selling_price : 0,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stock by location.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
