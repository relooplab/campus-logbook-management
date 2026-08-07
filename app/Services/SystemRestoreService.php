<?php

namespace App\Services;

use App\Services\Backup\BackupException;
use App\Services\Backup\BackupIntegrityChecker;
use App\Services\Backup\RestoreException;
use App\Services\Backup\RestoreValidationException;
use App\Services\Backup\ScopedRestoreExecutor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Restore sistem dari ZIP hasil SystemBackupService — mendukung restore full
 * (wipe & replace total) MAUPUN restore parsial (modul dan/atau institusi,
 * id-exact replace lewat ScopedRestoreExecutor). Restore selalu menerapkan
 * TEPAT scope yang tercatat di manifest.json ZIP-nya — mempersempit scope
 * dilakukan di sisi backup (pilih modul/institusi sebelum backup), bukan
 * dengan menyeleksi ulang saat restore.
 *
 * Urutan: validasi struktur ZIP (tanpa menyentuh apapun) -> extract ke temp
 * -> safety backup otomatis -> [full: file dulu baru DB] atau [parsial:
 * ScopedRestoreExecutor]. Kalau langkah file gagal (full), DB lama masih
 * utuh (recoverable). Kalau DB gagal, safety-backup sudah tersedia untuk
 * recovery manual.
 */
class SystemRestoreService
{
    /** Headroom disk relatif terhadap ukuran hasil extract. */
    private const DISK_HEADROOM_MULTIPLIER = 1.5;

    public function __construct(
        private readonly SystemBackupService $backupService,
        private readonly ScopedRestoreExecutor $scopedExecutor,
        private readonly BackupIntegrityChecker $integrityChecker,
    ) {
    }

    /**
     * @return array{safety_backup_path: string, integrity_issues: array<int,array{table:string,column:string,references:string,orphan_count:int,sample_ids:array<int,int>}>}
     *
     * @throws RestoreValidationException jika struktur ZIP tidak valid
     * @throws BackupException jika safety-backup otomatis gagal dibuat
     * @throws RestoreException jika gagal di tengah proses restore (setelah destruktif dimulai)
     */
    public function restore(string $zipPath): array
    {
        $manifest = $this->validateZip($zipPath);
        $isFull = ($manifest['is_full'] ?? false) === true;

        $extractDir = storage_path('framework/restore-tmp/'.(string) Str::uuid());
        File::ensureDirectoryExists($extractDir);

        $credentialsFile = null;
        $safetyBackupPath = null;
        $integrityIssues = [];

        try {
            $this->extractZip($zipPath, $extractDir);
            $this->guardDiskSpace($extractDir);

            // Safety backup dulu — SEBELUM langkah destruktif apapun.
            $safetyBackupPath = $this->backupService->create();
            Log::channel('audit')->info('System restore: safety backup created', [
                'by' => auth()->id(),
                'safety_backup_path' => $safetyBackupPath,
            ]);

            $credentialsFile = $this->makeCredentialsFile();

            if ($isFull) {
                // File dulu, baru DB — disk penuh (recoverable, DB lama masih
                // jalan) lebih mungkin terjadi daripada import DB gagal.
                $this->swapStorage($extractDir);
                $this->replaceDatabase($extractDir.'/database.sql', $credentialsFile);
            } else {
                $this->scopedExecutor->restore($extractDir, $manifest, $credentialsFile);

                // Wajib untuk jalur parsial: closure saat backup vs saat restore
                // bisa berbeda (mis. user yang direferensikan sudah dihapus sejak
                // backup diambil) — jangan diam-diam biarkan data jadi orphan.
                $integrityIssues = $this->integrityChecker->verify($manifest['tables_included'] ?? []);
                if ($integrityIssues !== []) {
                    Log::channel('audit')->warning('System restore: integrity issues detected after scoped restore', [
                        'by' => auth()->id(),
                        'source_zip' => $zipPath,
                        'issues' => $integrityIssues,
                    ]);
                }
            }

            Log::channel('audit')->info('System restore executed', [
                'by' => auth()->id(),
                'safety_backup' => $safetyBackupPath,
                'source_zip' => $zipPath,
                'is_full' => $isFull,
                'selection' => $manifest['selection'] ?? null,
                'tables_included' => $manifest['tables_included'] ?? null,
                'integrity_issues_count' => count($integrityIssues),
            ]);

            return ['safety_backup_path' => $safetyBackupPath, 'integrity_issues' => $integrityIssues];
        } finally {
            if ($credentialsFile && file_exists($credentialsFile)) {
                @unlink($credentialsFile);
            }
            File::deleteDirectory($extractDir);
        }
    }

    /**
     * Baca struktur ZIP TANPA extract penuh — pastikan database.sql &
     * manifest.json ada dan valid sebelum menyentuh apapun di sistem.
     *
     * @return array<string,mixed>
     */
    private function validateZip(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RestoreValidationException('File ZIP tidak valid atau rusak.');
        }

        try {
            if ($zip->locateName('database.sql') === false) {
                throw new RestoreValidationException('ZIP tidak berisi database.sql — bukan file backup sistem yang valid.');
            }

            $manifestRaw = $zip->getFromName('manifest.json');
            if ($manifestRaw === false) {
                throw new RestoreValidationException('ZIP tidak berisi manifest.json — bukan file backup sistem yang valid.');
            }

            $manifest = json_decode($manifestRaw, true);
            if (!is_array($manifest) || !array_key_exists('is_full', $manifest)) {
                throw new RestoreValidationException('manifest.json tidak valid atau rusak.');
            }

            return $manifest;
        } finally {
            $zip->close();
        }
    }

    private function extractZip(string $zipPath, string $destDir): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RestoreValidationException('Gagal membuka file ZIP untuk diekstrak.');
        }

        try {
            if (!$zip->extractTo($destDir)) {
                throw new RestoreValidationException('Gagal mengekstrak isi ZIP.');
            }
        } finally {
            $zip->close();
        }
    }

    private function guardDiskSpace(string $extractDir): void
    {
        $extractedSize = $this->directorySize($extractDir);
        $required = (int) ($extractedSize * self::DISK_HEADROOM_MULTIPLIER);
        $free = disk_free_space(storage_path());

        if ($free === false) {
            return;
        }

        if ($required > 0 && $free < $required) {
            throw new RestoreValidationException(sprintf(
                'Disk tidak cukup untuk melakukan restore. Perkiraan kebutuhan: %s, tersedia: %s.',
                $this->humanBytes($required),
                $this->humanBytes((int) $free)
            ));
        }
    }

    private function directorySize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 1).' '.$units[$i];
    }

    /**
     * Kosongkan storage/app/private & storage/app/public, lalu pindahkan
     * (rename per top-level entry — atomik) isi hasil extract ke tempatnya.
     * Kalau ZIP tidak punya folder storage/private atau storage/public
     * (backup tanpa file), target tetap dikosongkan (full snapshot = kosong).
     */
    private function swapStorage(string $extractDir): void
    {
        $this->clearDirectoryContents(storage_path('app/private'));
        $this->moveDirectoryContents($extractDir.'/storage/private', storage_path('app/private'));

        $this->clearDirectoryContents(storage_path('app/public'));
        $this->moveDirectoryContents($extractDir.'/storage/public', storage_path('app/public'));
    }

    private function clearDirectoryContents(string $dir): void
    {
        if (!is_dir($dir)) {
            File::ensureDirectoryExists($dir);

            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                File::deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
    }

    private function moveDirectoryContents(string $sourceDir, string $destDir): void
    {
        if (!is_dir($sourceDir)) {
            return;
        }

        File::ensureDirectoryExists($destDir);

        foreach (scandir($sourceDir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            rename($sourceDir.'/'.$item, $destDir.'/'.$item);
        }
    }

    private function makeCredentialsFile(): string
    {
        $config = config('database.connections.'.config('database.default'));

        $path = tempnam(sys_get_temp_dir(), 'lbta_dbcred_');
        if ($path === false) {
            throw new RestoreException('Gagal membuat file kredensial sementara.');
        }

        $lines = [
            '[client]',
            'user='.($config['username'] ?? ''),
            'password='.($config['password'] ?? ''),
            'host='.($config['host'] ?? '127.0.0.1'),
            'port='.($config['port'] ?? '3306'),
        ];

        file_put_contents($path, implode("\n", $lines)."\n");
        chmod($path, 0600);

        return $path;
    }

    private function replaceDatabase(string $sqlPath, string $credentialsFile): void
    {
        $this->dropAllTables($credentialsFile);
        $this->importDump($sqlPath, $credentialsFile);
    }

    private function dropAllTables(string $credentialsFile): void
    {
        $database = config('database.connections.'.config('database.default').'.database');

        $tables = DB::select(
            "SELECT table_name AS name FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'",
            [$database]
        );

        if ($tables === []) {
            return;
        }

        $statements = ['SET FOREIGN_KEY_CHECKS=0;'];
        foreach ($tables as $row) {
            $escaped = str_replace('`', '``', $row->name);
            $statements[] = "DROP TABLE IF EXISTS `{$escaped}`;";
        }
        $statements[] = 'SET FOREIGN_KEY_CHECKS=1;';

        $this->runMysql($credentialsFile, implode("\n", $statements));
    }

    private function importDump(string $sqlPath, string $credentialsFile): void
    {
        $input = (function () use ($sqlPath) {
            yield "SET FOREIGN_KEY_CHECKS=0;\n";

            $handle = fopen($sqlPath, 'rb');
            if ($handle === false) {
                return;
            }

            while (!feof($handle)) {
                yield fread($handle, 8192);
            }
            fclose($handle);

            yield "\nSET FOREIGN_KEY_CHECKS=1;\n";
        })();

        $this->runMysql($credentialsFile, $input);
    }

    private function runMysql(string $credentialsFile, mixed $input): void
    {
        $database = config('database.connections.'.config('database.default').'.database');

        $process = new Process(['mysql', '--defaults-extra-file='.$credentialsFile, $database]);
        $process->setTimeout(1800);
        $process->setInput($input);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RestoreException(
                'Proses restore database gagal di tengah jalan: '.trim($process->getErrorOutput())
                .' Safety-backup otomatis tersedia untuk recovery — lihat log audit.'
            );
        }
    }
}
