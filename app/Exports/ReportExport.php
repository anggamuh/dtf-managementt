<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReportExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $rows) {}
    public function headings(): array { return ['Tanggal', 'Nama', 'Status', 'Jumlah']; }
    public function collection(): Collection { return $this->rows->map(fn ($row) => [$row['date'], $row['name'], $row['status'], $row['amount']]); }
}
