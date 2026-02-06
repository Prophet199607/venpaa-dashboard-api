<?php

namespace App\Imports;

use App\Models\Author;
use App\Models\Product;
use App\Models\BookType;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\DocNumber;
use App\Models\StockMaster;
use App\Models\ProductAuthor;
use App\Models\ProductSupplier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BookImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        $activeLocations = Location::where('is_active', 1)->get();
        
        foreach ($rows as $row) {
            $this->processRow($row, $activeLocations);
        }
    }

    private function processRow($row, $activeLocations)
    {
        // Ensure mandatory book name is present
        if (!isset($row['book_name'])) {
            return;
        }

        $bookCode      = $row['book_code'] ?? null;
        $bookName      = $row['book_name'];
        $publisherCode = $row['publisher_code'] ?? null;
        $supplierCode  = $row['supplier_code'] ?? null;
        $authorCode    = $row['authors_code'] ?? null;
        $typeInput     = $row['type'] ?? null;
        $quantity      = $row['quantity'] ?? 0;
        $cost          = $row['cost'] ?? 0;
        $sellingPrice  = $row['selling_price'] ?? 0;

        // 1. Identify Product by Book Code (stored in barcode or prod_code)
        $product = null;
        if ($bookCode) {
            $product = Product::where('prod_code', $bookCode)
                             ->orWhere('barcode', $bookCode)
                             ->first();
        }

        // 2. Data Preparation
        
        // Handle Book Type
        $bookTypeCode = null;
        if ($typeInput) {
            $bookType = BookType::where('bkt_name', $typeInput)->first();

            if ($bookType) {
                $bookTypeCode = $bookType->bkt_code;
            } else {
                // Create new Book Type
                $btDocNumber = DocNumber::where('type', 'BookType')->first();
                $newBktCode = 'BT-' . time();

                if ($btDocNumber) {
                    $btCodeData = $btDocNumber->getDocCode();
                    $newBktCode = $btCodeData['code'];
                }

                $newBookType = BookType::create([
                    'bkt_code' => $newBktCode,
                    'bkt_name' => $typeInput,
                    'status' => 1,
                    'created_by' => auth()->id() ?? 1
                ]);
                
                $bookTypeCode = $newBookType->bkt_code;
            }
        }

        // Prepare Product Data
        $productData = [
            'prod_name'      => $bookName,
            'purchase_price' => $cost,
            'selling_price'  => $sellingPrice,
            'department'     => '10',
            'book_type'      => $bookTypeCode,
            'publisher'      => $publisherCode,
            'unit_name'      => 'NOS',
            'barcode'        => $bookCode,
            'category'       => null,
            'sub_category'   => null,
            'alert_qty'      => null,
            'pack_size'      => '1',
        ];

        // 3. Create or Update Product
        if ($product) {
            // Update Existing
            $productData['updated_by'] = auth()->id();
            $product->update($productData);
        } else {
            // Create New
            $docNumber = DocNumber::where('type', 'Product')->first();
            $prod_code = 'GEN-' . time();
            if ($docNumber) {
                $codeData = $docNumber->getDocCode();
                $prod_code = $codeData['code'];
            }

            $productData['prod_code'] = $prod_code;
            $productData['status'] = 1;
            $productData['created_by'] = auth()->id() ?? 1;

            $product = Product::create($productData);
        }

        // 4. Handle Relationships (Authors, Suppliers)

        // Authors
        if (!empty($authorCode)) {
            // Clear existing for update
            ProductAuthor::where('prod_code', $product->prod_code)->delete();
            
            $codes = array_unique(array_filter(array_map('trim', explode(',', $authorCode))));
            
            if (!empty($codes)) {
                $authors = Author::whereIn('auth_code', $codes)->get();
                foreach ($authors as $author) {
                    ProductAuthor::create([
                        'prod_code' => $product->prod_code,
                        'author_id' => $author->id,
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);
                }
            }
        }

        // Suppliers
        if ($supplierCode) {
            // Clear existing for update
            ProductSupplier::where('prod_code', $product->prod_code)->delete();

            $codes = explode(',', $supplierCode);
            foreach ($codes as $code) {
                $code = trim($code);
                $supplier = Supplier::where('sup_code', $code)->first();
                if ($supplier) {
                    ProductSupplier::create([
                        'prod_code' => $product->prod_code,
                        'supplier_id' => $supplier->id,
                        'created_by' => auth()->id() ?? 1,
                        'updated_by' => auth()->id(),
                    ]);
                }
            }
        }

        // 5. Handle StockMaster (iid = CREATE)
        foreach ($activeLocations as $location) {
            $locQty = ($location->loca_code == '002') ? $quantity : 0.000;
            $stock = StockMaster::where('prod_code', $product->prod_code)
                ->where('location', $location->loca_code)
                ->where('iid', 'CREATE')
                ->first();

            if ($stock) {
                $stock->update([
                    'qty' => $locQty,
                    'purchase_price' => $cost,
                    'selling_price' => $sellingPrice,
                    'updated_at' => now(),
                ]);
            } else {
                StockMaster::create([
                    'location' => $location->loca_code,
                    'transaction_date' => '',
                    'doc_no' => '',
                    'prod_code' => $product->prod_code,
                    'iid' => 'CREATE',
                    'qty' => $locQty,
                    'purchase_price' => $cost,
                    'selling_price' => $sellingPrice,
                    'amount' => 0.00,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
