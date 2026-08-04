<?php

namespace App\Http\Controllers;

use App\Models\LogbookHarianKp;
use App\Models\MahasiswaTa;
use App\Models\WorkspaceFile;
use App\Notifications\ActivityNotification;
use App\Services\StorageUsageService;
use App\Support\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * "Penyimpanan Saya" — halaman manajemen penyimpanan untuk dosen.
 * Menampilkan file yang dibebankan ke kuota dosen (workspace mahasiswa
 * + foto logbook harian KP) dan memungkinkan dosen menghapusnya.
 */
class StorageController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isDosen(), 403, 'Halaman ini khusus dosen.');

        $usageService = app(StorageUsageService::class);
        $programIds = $usageService->dosenProgramIds($user);

        $programs = MahasiswaTa::whereIn('id', $programIds)
            ->with(['mahasiswa', 'pembimbing1', 'pembimbing2'])
            ->orderBy('created_at')
            ->get();

        $workspaceFiles = WorkspaceFile::whereIn('mahasiswa_ta_id', $programIds)
            ->with(['mahasiswaTa.mahasiswa', 'uploader'])
            ->orderByDesc('created_at')
            ->get();

        $logbookHarian = LogbookHarianKp::whereIn('mahasiswa_ta_id', $programIds)
            ->with(['mahasiswaTa.mahasiswa'])
            ->where(fn ($q) => $q->whereNotNull('foto_1')->orWhereNotNull('foto_2'))
            ->orderByDesc('tanggal')
            ->get();

        $totalBytes = $usageService->totalBytes($user);
        $limitBytes = Feature::storageLimitMb($user) * 1048576;
        $usedLabel = $usageService->formatBytes($totalBytes);
        $limitLabel = $limitBytes > 0 ? $usageService->formatBytes($limitBytes) : 'Tak terbatas';
        $pct = $limitBytes > 0 ? min(100, round($totalBytes / $limitBytes * 100)) : 0;

        return view('storage.index', compact(
            'programs', 'workspaceFiles', 'logbookHarian',
            'totalBytes', 'limitBytes', 'usedLabel', 'limitLabel', 'pct'
        ));
    }

    public function destroyWorkspace(Request $request, WorkspaceFile $file): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isDosen(), 403);

        $ta = $file->mahasiswaTa;
        abort_unless($ta && $ta->isPembimbing($user), 403, 'Anda bukan pembimbing program ini.');

        $fileInfo = $file->original_name;
        Storage::disk('local')->delete($file->path);
        $file->delete();

        if ($ta->mahasiswa) {
            $this->bestEffort(fn () => $ta->mahasiswa->notify(new ActivityNotification(
                "Dosen Anda menghapus file workspace '{$fileInfo}' untuk mengelola penyimpanan.",
                route('workspace.index', $ta),
                'File Workspace Dihapus Dosen'
            )));
        }

        return back()->with('success', "File workspace '{$fileInfo}' berhasil dihapus.");
    }

    public function destroyLogbookHarian(Request $request, LogbookHarianKp $entry, string $foto): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isDosen(), 403);
        abort_unless(in_array($foto, ['foto_1', 'foto_2'], true), 404);

        $ta = $entry->mahasiswaTa;
        abort_unless($ta && $ta->isPembimbing($user), 403, 'Anda bukan pembimbing program ini.');

        $path = $entry->{$foto};
        if (!$path) {
            return back()->with('error', 'Foto tidak ditemukan.');
        }

        Storage::disk('local')->delete($path);
        $entry->update([$foto => null]);

        if ($ta->mahasiswa) {
            $this->bestEffort(fn () => $ta->mahasiswa->notify(new ActivityNotification(
                "Dosen Anda menghapus foto logbook harian KP (tanggal {$entry->tanggal->format('d M Y')}) untuk mengelola penyimpanan.",
                route('logbook-harian.index', $ta),
                'Foto Logbook Harian Dihapus Dosen'
            )));
        }

        return back()->with('success', 'Foto logbook harian berhasil dihapus.');
    }
}