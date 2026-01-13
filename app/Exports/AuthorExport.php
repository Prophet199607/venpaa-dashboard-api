<?php

namespace App\Exports;

use App\Models\Author;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AuthorExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Author::where('status', 1)->get();
    }

    public function headings(): array
    {
        return [
            'Code',
            'Author Name',
            'Name (Other Language)',
            'Description',
        ];
    }

    public function map($author): array
    {
        return [
            $author->auth_code,
            $author->auth_name,
            $author->auth_name_other_language,
            $author->description,
        ];
    }
}
