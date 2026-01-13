<?php

namespace App\Imports;

use App\Models\Author;
use App\Models\DocNumber;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AuthorImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        if (!isset($row['authors_name'])) {
            return null;
        }

        $docNumber = DocNumber::where('type', 'Author')->first();
        
        $auth_code = 'AUT-' . time();
        if ($docNumber) {
            $codeData = $docNumber->getDocCode();
            $auth_code = $codeData['code'];
        }

        return new Author([
            'auth_code'   => $auth_code,
            'auth_name'   => $row['authors_name'],
            'status'     => 1,
            'created_by' => auth()->id(),
        ]);
    }
}
