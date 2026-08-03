<?php

namespace App\Http\Controllers;

use App\Exports\MahasiswaTaExport;
use App\Models\MahasiswaTa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    /**
     * Rekap bimbingan per mahasiswa (PDF resmi via DomPDF).
     */
    public function exportPdf(MahasiswaTa $mahasiswaTa)
    {
        $this->authorizeExport($mahasiswaTa);

        $mahasiswaTa->load(['mahasiswa', 'pembimbing1', 'pembimbing2']);

        $entries = $mahasiswaTa->entries()->orderBy('created_at')->get();
        $approved = $entries->where('status', 'approved')->count();

        $pdf = Pdf::loadView('exports.rekap-bimbingan', [
            'mahasiswaTa' => $mahasiswaTa,
            'entries' => $entries,
            'approved' => $approved,
            'target' => $mahasiswaTa->target_sesi,
        ]);

        $filename = 'rekap-bimbingan-'.$mahasiswaTa->mahasiswa->identifier.'-'.now()->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Data bimbingan per dosen (Excel via Laravel Excel).
     */
    public function exportExcel(MahasiswaTa $mahasiswaTa)
    {
        $this->authorizeExport($mahasiswaTa);

        $filename = 'bimbingan-'.$mahasiswaTa->mahasiswa->identifier.'-'.now()->format('Ymd').'.xlsx';

        return Excel::download(new MahasiswaTaExport($mahasiswaTa), $filename);
    }

    /**
     * Rekap bimbingan + menguji per dosen untuk lampiran BKD (DomPDF).
     */
    public function exportSidangPdf()
    {
        $user = auth()->user();
        if (!$user->isDosen() && !$user->isAdmin()) {
            abort(403);
        }

        $dosen = $user->isDosen() ? $user : (\App\Models\User::role('dosen')->first() ?? $user);

        $bimbingan = \App\Models\MahasiswaTa::bimbinganOleh($dosen)->with('mahasiswa')->get();
        $sidangs = \App\Models\Sidang::where('penguji_id', $dosen->id)
            ->with('mahasiswaTa.mahasiswa')
            ->orderByDesc('tanggal')
            ->get();

        $pdf = Pdf::loadView('exports.rekap-dosen', [
            'dosen' => $dosen,
            'bimbingan' => $bimbingan,
            'sidangs' => $sidangs,
        ]);

        $filename = 'rekap-dosen-'.$dosen->identifier.'-'.now()->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }

    private function authorizeExport(MahasiswaTa $mahasiswaTa): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isMahasiswa() && $mahasiswaTa->isMember($user)) {
            return;
        }

        if ($user->isDosen() && $mahasiswaTa->isPembimbing($user)) {
            return;
        }

        abort(403, 'Anda tidak berhak mengakses laporan ini.');
    }
}
