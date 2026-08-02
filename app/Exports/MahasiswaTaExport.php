<?php

namespace App\Exports;

use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MahasiswaTaExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(public MahasiswaTa $mahasiswaTa)
    {
    }

    public function collection()
    {
        return $this->mahasiswaTa->entries()->orderBy('created_at')->get();
    }

    public function headings(): array
    {
        return [
            'Sesi',
            'Jenis',
            'Tanggal Bimbingan',
            'Topik',
            'Status',
            'Ringkasan Perbaikan',
            'Feedback Dosen',
        ];
    }

    public function map($entry): array
    {
        return [
            $entry->jenis === LogbookEntry::JENIS_REVISI ? '—' : $entry->sesi_ke,
            ucfirst($entry->jenis),
            $entry->tanggal_tampil?->format('d/m/Y') ?? '—',
            $entry->topik ?? '—',
            ucfirst($entry->status),
            $entry->progres_kendala,
            $entry->feedback_dosen ?? '—',
        ];
    }
}
