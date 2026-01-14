<?php

namespace App\Http\Controllers\Master;

use App\Models\Author;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Location;
use App\Models\DocNumber;
use App\Models\StockMaster;
use App\Exports\BookExport;
use App\Imports\BookImport;
use Illuminate\Http\Request;
use App\Models\ProductImage;
use App\Models\ProductAuthor;
use App\Models\ProductSupplier;
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
                ->with(['authors', 'category', 'subCategory', 'department', 'bookType', 'publisher', 'suppliers', 'images'])
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
                ->with(['authors', 'subCategory.category.department', 'bookType', 'publisher', 'suppliers', 'images'])
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

            unset($data['author']);
            unset($data['supplier']);
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
            $product->load(['bookType', 'department', 'category', 'subCategory', 'publisher', 'suppliers', 'authors', 'images']);

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

            unset($data['author']);
            unset($data['supplier']);
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

            DB::commit();

            // Load relationships for the resource
            $product->load(['bookType', 'department', 'category', 'subCategory', 'publisher', 'suppliers', 'suppliers', 'images']);

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
