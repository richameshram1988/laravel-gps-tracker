<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TravelReportExport implements FromArray, WithHeadings
{
    public function __construct(private array $rows)
    {
    }

    public function headings(): array
    {
        return ['User', 'Date', 'Distance (km)', 'First Location', 'Last Location'];
    }

    public function array(): array
    {
        return array_map(fn($r) => [
            $r['user'],
            $r['date'],
            $r['distance_km'],
            $r['first_location'],
            $r['last_location'],
        ], $this->rows);
    }
}