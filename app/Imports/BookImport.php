<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\DocNumber;
use App\Models\BookType;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BookImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        if (!isset($row['book_name'])) {
            return null;
        }

        $docNumber = DocNumber::where('type', 'Product')->first();
        
        $prod_code = 'GEN-' . time();
        if ($docNumber) {
            $codeData = $docNumber->getDocCode();
            $prod_code = $codeData['code'];
        }

        // Handle Book Type
        $bookTypeCode = null;
        $typeInput = $row['type'] ?? null;

        if ($typeInput) {
            // Check if Book Type exists by name
            $bookType = BookType::where('bkt_name', $typeInput)->first();

            if ($bookType) {
                // Use existing code
                $bookTypeCode = $bookType->bkt_code;
            } else {
                // Create new Book Type
                $btDocNumber = DocNumber::where('type', 'BookType')->first();
                $newBktCode = 'BT-' . time(); // Fallback

                if ($btDocNumber) {
                    $btCodeData = $btDocNumber->getDocCode();
                    $newBktCode = $btCodeData['code'];
                    // BookType model boot method handles increment, but only on creation.
                    // IMPORTANT: If we are creating manually here, we might need to rely on the model event.
                }

                $newBookType = BookType::create([
                    'bkt_code' => $newBktCode,
                    'bkt_name' => $typeInput,
                    'status' => 1,
                    'created_by' => auth()->id()
                ]);
                
                $bookTypeCode = $newBookType->bkt_code;
            }
        }

        return new Product([
            'prod_code'      => $prod_code,
            'prod_name'      => $row['book_name'],
            'alert_qty'      => $row['quantity'] ?? 0,
            'purchase_price' => $row['cost'] ?? 0,
            'selling_price'  => $row['selling_price'] ?? 0,
            'department'     => '10',
            'book_type'      => $bookTypeCode,
            'category'       => null,
            'sub_category'   => null,
            'status'         => 1,
            'unit_name'      => 'NOS',
            'created_by'     => auth()->id(),
        ]);
    }
}
