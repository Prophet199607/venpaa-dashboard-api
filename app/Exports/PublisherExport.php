<?php

namespace App\Exports;

use App\Models\Publisher;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PublisherExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Publisher::where('status', 1)->get();
    }

    public function headings(): array
    {
        return [
            'Code',
            'Publisher Name',
            'Website',
            'Contact',
            'Email',
            'Description',
        ];
    }

    public function map($publisher): array
    {
        return [
            $publisher->pub_code,
            $publisher->pub_name,
            $publisher->website,
            $publisher->contact,
            $publisher->email,
            $publisher->description,
        ];
    }
}
