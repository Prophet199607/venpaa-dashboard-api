<?php

namespace App\Imports;

use App\Models\Publisher;
use App\Models\DocNumber;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class PublisherImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    public function model(array $row)
    {
        $name = $row['publisher_name'] ?? $row['pub_name'] ?? $row['supplier_name'] ?? $row['sup_name'] ?? null;
        
        if (is_null($name) || trim($name) === '') {
            return null;
        }

        $docNumber = DocNumber::where('type', 'Publisher')->first();
        
        $pub_code = 'PUB-' . time();
        if ($docNumber) {
            $codeData = $docNumber->getDocCode();
            $pub_code = $codeData['code'];
        }

        return new Publisher([
            'pub_code'    => $pub_code,
            'pub_name'    => $name,
            'website'     => $row['website'] ?? null,
            'contact'     => $row['contact'] ?? $row['mobile'] ?? $row['telephone'] ?? null,
            'email'       => $row['email'] ?? null,
            'description' => $row['description'] ?? null,
            'status'      => 1,
            'created_by'  => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'publisher_name' => 'required_without_all:pub_name,supplier_name,sup_name',
            'pub_name'       => 'required_without_all:publisher_name,supplier_name,sup_name',
            'supplier_name'  => 'required_without_all:publisher_name,pub_name,sup_name',
            'sup_name'       => 'required_without_all:publisher_name,pub_name,supplier_name',
        ];
    }
}
