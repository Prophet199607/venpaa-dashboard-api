<?php

namespace App\Imports;

use App\Models\Publisher;
use App\Models\DocNumber;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PublisherImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        if (!isset($row['publisher_name'])) {
            return null;
        }

        $docNumber = DocNumber::where('type', 'Publisher')->first();
        
        $pub_code = 'PUB-' . time();
        if ($docNumber) {
            $codeData = $docNumber->getDocCode();
            $pub_code = $codeData['code'];
        }

        return new Publisher([
            'pub_code'   => $pub_code,
            'pub_name'   => $row['publisher_name'],
            'status'     => 1,
            'created_by' => auth()->id(),
        ]);
    }
}
