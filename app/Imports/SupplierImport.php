<?php

namespace App\Imports;

use App\Models\Supplier;
use App\Models\DocNumber;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class SupplierImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    public function model(array $row)
    {
        $name = $row['supplier_name'] ?? $row['sup_name'] ?? $row['publisher_name'] ?? $row['pub_name'] ?? null;
        
        if (is_null($name) || trim($name) === '') {
            return null;
        }

        $docNumber = DocNumber::where('type', 'Supplier')->first();
        
        $sup_code = 'SUP-' . time();
        if ($docNumber) {
            $codeData = $docNumber->getDocCode();
            $sup_code = $codeData['code'];
        }

        return new Supplier([
            'sup_code'    => $sup_code,
            'sup_name'    => $name,
            'company'     => $row['company'] ?? null,
            'address'     => $row['address'] ?? null,
            'mobile'      => $row['mobile'] ?? $row['contact'] ?? null,
            'telephone'   => $row['telephone'] ?? null,
            'email'       => $row['email'] ?? null,
            'description' => $row['description'] ?? null,
            'status'      => 1,
            'created_by'  => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'supplier_name'  => 'required_without_all:sup_name,publisher_name,pub_name',
            'sup_name'       => 'required_without_all:supplier_name,publisher_name,pub_name',
            'publisher_name' => 'required_without_all:supplier_name,sup_name,pub_name',
            'pub_name'       => 'required_without_all:supplier_name,sup_name,publisher_name',
        ];
    }
}
