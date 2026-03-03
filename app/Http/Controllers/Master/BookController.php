<?php

namespace App\Http\Controllers\Master;

use App\Models\Iid;
use App\Models\Author;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Location;
use App\Models\DocNumber;
use App\Models\StockMaster;
use App\Exports\BookExport;
use App\Imports\BookImport;
use Illuminate\Http\Request;
use App\Models\SubCategory;
use App\Models\ProductImage;
use App\Models\ProductAuthor;
use App\Models\ProductSupplier;
use App\Models\ProductSubCategory;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
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
                ->with(['authors', 'category', 'subCategories', 'department', 'bookType', 'publisher', 'suppliers', 'images', 'languageRelation'])
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
                ->with(['authors', 'subCategories.category.department', 'department.categories', 'bookType', 'publisher', 'suppliers', 'images', 'languageRelation'])
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
                'data' => new BookResource($product)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

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
                    'message' => 'Book not found',
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

            // Handle authors data
            $authorCodes = [];
            if ($request->has('author') && !empty($request->input('author'))) {
                $authorCodes = explode(',', $request->input('author'));
                // Validate that all author codes exist
                $existingAuthors = Author::whereIn('auth_code', $authorCodes)->pluck('auth_code')->toArray();
                $nonExistingAuthors = array_diff($authorCodes, $existingAuthors);

                if (!empty($nonExistingAuthors)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Some authors do not exist: ' . implode(', ', $nonExistingAuthors)
                    ], 422);
                }
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

            // Separate image data from book data
            $images = $request->file('images');
            unset($data['images']);

            // Handle Book image upload
            if ($request->hasFile('prod_image')) {
                $prodImage = $request->file('prod_image');
                $filename = $data['prod_code'] . '.' . $prodImage->getClientOriginalExtension();
                // $prodImagePath = $prodImage->storeAs('products/main', $filename, 'public');
                $prodImagePath = $prodImage->storeAs('products/main', $filename, 's3');
                $data['prod_image'] = $prodImagePath;
            } else {
                $data['prod_image'] = $data['prod_code'];
            }

            unset($data['author']);
            unset($data['supplier']);
            unset($data['sub_category']);

            $data['barcode'] = $data['prod_code'];
            $product = Product::create($data);

            // Handle authors data
            if (!empty($authorCodes)) {
                foreach ($authorCodes as $authorCode) {
                    $author = Author::where('auth_code', $authorCode)->first();
                    if ($author) {
                        ProductAuthor::create([
                            'prod_code' => $product->prod_code,
                            'author_id' => $author->id,
                            'created_by' => auth()->id()
                        ]);
                    }
                }
            }

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
            $product->load(['bookType', 'department', 'category', 'subCategories', 'publisher', 'suppliers', 'authors', 'images', 'languageRelation']);

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

            // Handle authors data
            $authorCodes = [];
            if ($request->has('author') && !empty($request->input('author'))) {
                $authorCodes = explode(',', $request->input('author'));
                // Validate that all author codes exist
                $existingAuthors = Author::whereIn('auth_code', $authorCodes)->pluck('auth_code')->toArray();
                $nonExistingAuthors = array_diff($authorCodes, $existingAuthors);

                if (!empty($nonExistingAuthors)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Some authors do not exist: ' . implode(', ', $nonExistingAuthors)
                    ], 422);
                }
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

            // Separate image data from book data
            $images = $request->file('images');
            unset($data['images']);

            // Handle book image update
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

            unset($data['author']);
            unset($data['supplier']);
            unset($data['sub_category']);
            if (isset($data['prod_code'])) {
                $data['barcode'] = $data['prod_code'];
            }
            $product->update($data);

            // Sync authors - delete existing and create new rows
            if ($authorCodes !== null) {
                // Delete existing author relationships
                ProductAuthor::where('prod_code', $product->prod_code)->delete();

                // Create new author relationships
                foreach ($authorCodes as $authorCode) {
                    $author = Author::where('auth_code', $authorCode)->first();
                    if ($author) {
                        ProductAuthor::create([
                            'prod_code' => $product->prod_code,
                            'author_id' => $author->id,
                            'created_by' => auth()->id(),
                            'updated_by' => auth()->id()
                        ]);
                    }
                }
            }

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
            $product->load(['bookType', 'department', 'category', 'subCategories', 'publisher', 'suppliers', 'authors', 'images', 'languageRelation']);

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

    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
            ]);

            Excel::import(new BookImport, $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Books imported successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import books',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function export()
    {
        return Excel::download(new BookExport, 'books.xlsx');
    }
}
