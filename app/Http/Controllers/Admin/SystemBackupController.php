<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Backup\BackupException;
use App\Services\Backup\BackupModuleRegistry;
use App\Services\Backup\RestoreException;
use App\Services\Backup\RestoreValidationException;
use App\Services\SystemBackupService;
use App\Services\SystemRestoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backup & restore seluruh sistem (system_admin). Controller ini murni
 * memanggil SystemBackupService/SystemRestoreService langsung (bukan
 * Artisan::call()) supaya exception handling & return value bersih.
 */
class SystemBackupController extends Controller
{
    /** Frasa yang wajib diketik ulang untuk konfirmasi restore. */
    public const CONFIRMATION_PHRASE = 'HAPUS SEMUA DATA';

    public function index(Request $request, BackupModuleRegistry $registry): View
    {
        $this->authorizeSystemAdmin($request);

        return view('admin.system.backup', [
            'modules' => $registry->definitions(),
        ]);
    }

    public function store(Request $request, SystemBackupService $service): RedirectResponse|StreamedResponse
    {
        $this->authorizeSystemAdmin($request);

        // Belt-and-suspenders di luar Dockerfile ini override — mysqldump+zip
        // pada data besar bisa lebih lama dari default execution time PHP.
        set_time_limit(1800);

        $validated = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string'],
        ]);

        $modules = $validated['modules'] ?? null;

        try {
            $zipPath = $service->create($modules ?: null);
        } catch (BackupException $e) {
            return back()->with('error', 'Backup gagal: '.$e->getMessage());
        }

        $filename = 'backup-'.now()->format('Ymd-His').'.zip';

        return response()->streamDownload(function () use ($zipPath) {
            readfile($zipPath);
            @unlink($zipPath);
        }, $filename, ['Content-Type' => 'application/zip']);
    }

    public function restore(Request $request, SystemRestoreService $service): RedirectResponse
    {
        $this->authorizeSystemAdmin($request);

        set_time_limit(1800);

        $validated = $request->validate([
            // max di sini (KB) harus <= upload_max_filesize/post_max_size di Dockerfile (512M).
            'backup_file' => ['required', 'file', 'mimes:zip', 'max:524288'],
            'confirmation' => ['required', 'in:'.self::CONFIRMATION_PHRASE],
        ]);

        $uploadDir = storage_path('framework/restore-tmp');
        File::ensureDirectoryExists($uploadDir);

        // DI LUAR storage/app/private — kalau disimpan di situ, akan ke-wipe
        // oleh proses restore-nya sendiri sebelum sempat dibaca.
        $filename = (string) Str::uuid().'.zip';
        $request->file('backup_file')->move($uploadDir, $filename);
        $uploadPath = $uploadDir.'/'.$filename;

        try {
            $result = $service->restore($uploadPath);
        } catch (RestoreValidationException $e) {
            return back()->with('error', 'Backup tidak valid: '.$e->getMessage());
        } catch (BackupException|RestoreException $e) {
            return back()->with('error', $e->getMessage());
        } finally {
            @unlink($uploadPath);
        }

        return back()->with(
            'success',
            'Restore berhasil. Safety-backup (kondisi sebelum restore) disimpan di server: '.$result['safety_backup_path']
        );
    }

    private function authorizeSystemAdmin(Request $request): void
    {
        // Defense-in-depth: middleware role:system_admin sudah gate route ini,
        // tapi ini aksi paling konsekuensial di seluruh aplikasi.
        abort_unless($request->user()->isSystemAdmin(), 403);
    }
}
