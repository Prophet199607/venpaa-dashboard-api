<?php

namespace App\Exports;

use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SupplierExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Supplier::where('status', 1)->get();
    }

    public function headings(): array
    {
        return [
            'Code',
            'Supplier Name',
            'Company',
            'Address',
            'Mobile',
            'Telephone',
            'Email',
            'Description',
        ];
    }

    public function map($supplier): array
    {
        return [
            $supplier->sup_code,
            $supplier->sup_name,
            $supplier->company,
            $supplier->address,
            $supplier->mobile,
            $supplier->telephone,
            $supplier->email,
            $supplier->description,
        ];
    }
}
