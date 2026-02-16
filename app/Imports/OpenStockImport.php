<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Location;
use App\Models\DocNumber;
use App\Models\StockMaster;
use App\Models\TransactionHeader;
use App\Models\TransactionDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class OpenStockImport implements ToCollection, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    protected $location;

    public function __construct(string $location)
    {
        $this->location = $location;
    }

    public function collection(Collection $rows)
    {
        $filteredRows = $rows->filter(function ($row) {
            return isset($row['quantity']) && $row['quantity'] > 0;
        });

        if ($filteredRows->isEmpty()) {
            return;
        }

        $locationModel = Location::where('loca_code', $this->location)->first();

        // Group rows by supplier_code to create separate headers if needed
        $groupedRows = $filteredRows->groupBy(function ($row) {
            return $row['supplier_code'] ?? '';
        });

        DB::beginTransaction();
        try {
            foreach ($groupedRows as $supplierCode => $rowsForSupplier) {
                $docNumberRecord = DocNumber::where('type', 'OpenStock')->first();
                
                if (!$docNumberRecord) {
                    $docNumberRecord = DocNumber::create([
                        'type' => 'OpenStock',
                        'prefix' => 'OPS',
                        'length' => 8,
                        'last_id' => 0
                    ]);
                }

                $docCodeData = $docNumberRecord->getDocCode($this->location);
                $doc_no = $docCodeData['code'];
                $docNumberRecord->incrementLastId();

                $header = TransactionHeader::create([
                    'location' => $this->location,
                    'doc_no' => $doc_no,
                    'temp_doc_no' => $doc_no,
                    'document_date' => now(),
                    'transaction_date' => now(),
                    'iid' => 'OPS',
                    'supplier_code' => $supplierCode ?: null,
                    'delivery_location' => $this->location,
                    'delivery_address' => $locationModel ? $locationModel->delivery_address : null,
                    'remarks_ref' => 'Opening Stock Import',
                    'created_by' => auth()->id() ?? 1,
                    'subtotal' => 0,
                    'net_total' => 0,
                ]);

                $lineNo = 1;

                foreach ($rowsForSupplier as $row) {
                    $productCode = $row['product_code'] ?? $row['book_code'] ?? null;
                    $quantity = $row['quantity'];
                    
                    if (!$productCode) continue;

                    $product = Product::where('prod_code', $productCode)
                                     ->orWhere('barcode', $productCode)
                                     ->first();

                    if (!$product) continue;

                    $purchasePrice = $row['cost'] ?? $product->purchase_price ?? 0;
                    $sellingPrice = $row['selling_price'] ?? $product->selling_price ?? 0;
                    
                    $lineAmount = $quantity * $purchasePrice;

                    TransactionDetail::create([
                        'transaction_header_id' => $header->id,
                        'doc_no' => $doc_no,
                        'line_no' => $lineNo++,
                        'prod_code' => $product->prod_code,
                        'prod_name' => $product->prod_name,
                        'qty' => $quantity,
                        'pack_qty' => $quantity,
                        'total_qty' => $quantity,
                        'pack_size' => 1,
                        'purchase_price' => $purchasePrice,
                        'selling_price' => $sellingPrice,
                        'amount' => $lineAmount,
                        'location' => $this->location,
                        'iid' => 'OPS',
                        'created_by' => auth()->id() ?? 1,
                    ]);

                    StockMaster::create([
                        'location' => $this->location,
                        'transaction_date' => now(),
                        'doc_no' => $doc_no,
                        'prod_code' => $product->prod_code,
                        'iid' => 'OPS',
                        'qty' => $quantity,
                        'purchase_price' => $purchasePrice,
                        'selling_price' => $sellingPrice,
                        'amount' => 0.00,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
