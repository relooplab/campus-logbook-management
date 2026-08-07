<?php

namespace App\Services;

use App\Services\Backup\BackupException;
use App\Services\Backup\BackupModuleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Membuat backup sistem: dump database (mysqldump) + file storage, dibungkus
 * jadi satu ZIP. Mendukung full backup (seluruh sistem) atau selective backup
 * per-modul (lihat config/backup_modules.php).
 *
 * Restore modul parsial BELUM didukung (lihat SystemRestoreService) — backup
 * parsial saat ini murni untuk keperluan arsip/migrasi manual.
 */
class SystemBackupService
{
    /** Headroom disk yang diminta relatif terhadap estimasi ukuran backup. */
    private const DISK_HEADROOM_MULTIPLIER = 2.5;

    public function __construct(
        private readonly BackupModuleRegistry $registry,
    ) {
    }

    /**
     * @param array<int,string>|null $moduleKeys null/[] = full backup (seluruh sistem)
     * @return string path absolut ke file ZIP hasil backup (di storage/framework/backup-tmp)
     *
     * @throws BackupException
     */
    public function create(?array $moduleKeys = null): string
    {
        $isFull = empty($moduleKeys);
        $modules = $isFull ? [] : $this->registry->resolveDependencyClosure($moduleKeys);
        $tables = $isFull ? [] : $this->registry->tablesForModules($modules);

        $this->guardDiskSpace();

        $tmpRoot = storage_path('framework/backup-tmp');
        File::ensureDirectoryExists($tmpRoot);

        $workDir = $tmpRoot.'/'.(string) Str::uuid();
        File::ensureDirectoryExists($workDir);

        $credentialsFile = null;

        try {
            $credentialsFile = $this->makeCredentialsFile();
            $sqlPath = $workDir.'/database.sql';

            $this->dumpDatabase($sqlPath, $credentialsFile, $tables);

            $zipPath = $tmpRoot.'/system-backup-'.now()->format('Ymd-His').'-'.Str::random(6).'.zip';
            $this->buildZip($zipPath, $sqlPath, $isFull, $modules, $tables);

            return $zipPath;
        } finally {
            if ($credentialsFile && file_exists($credentialsFile)) {
                @unlink($credentialsFile);
            }
            File::deleteDirectory($workDir);
        }
    }

    /**
     * Estimasi ukuran DB + storage, bandingkan dengan disk_free_space, minta
     * headroom ~2.5x supaya proses dump+zip tidak korup di tengah jalan
     * karena disk penuh. Selalu pakai estimasi FULL (konservatif) meski
     * backup yang diminta parsial — lebih aman daripada meremehkan kebutuhan.
     */
    private function guardDiskSpace(): void
    {
        $dbSizeBytes = (int) (DB::selectOne(
            'SELECT SUM(data_length + index_length) AS size FROM information_schema.TABLES WHERE table_schema = ?',
            [DB::getDatabaseName()]
        )->size ?? 0);

        $storageSizeBytes = $this->directorySize(storage_path('app/private'))
            + $this->directorySize(storage_path('app/public'));

        $estimatedBytes = $dbSizeBytes + $storageSizeBytes;
        $requiredBytes = (int) ($estimatedBytes * self::DISK_HEADROOM_MULTIPLIER);

        $freeBytes = disk_free_space(storage_path());

        if ($freeBytes === false) {
            return;
        }

        if ($requiredBytes > 0 && $freeBytes < $requiredBytes) {
            throw new BackupException(sprintf(
                'Disk tidak cukup untuk membuat backup. Perkiraan kebutuhan: %s, tersedia: %s.',
                $this->humanBytes($requiredBytes),
                $this->humanBytes((int) $freeBytes)
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
     * Buat file kredensial DB sementara ([client] section) — mencegah
     * password bocor lewat ps aux / proc/[pid]/cmdline (yang terjadi kalau
     * dipakai --password= langsung di argumen CLI).
     */
    private function makeCredentialsFile(): string
    {
        $config = config('database.connections.'.config('database.default'));

        $path = tempnam(sys_get_temp_dir(), 'lbta_dbcred_');
        if ($path === false) {
            throw new BackupException('Gagal membuat file kredensial sementara.');
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

    /**
     * @param array<int,string> $tables kosong = full dump (seluruh database, tanpa filter tabel)
     */
    private function dumpDatabase(string $sqlPath, string $credentialsFile, array $tables): void
    {
        $database = config('database.connections.'.config('database.default').'.database');

        $command = [
            'mysqldump',
            '--defaults-extra-file='.$credentialsFile,
            '--single-transaction',
            '--no-tablespaces',
            '--skip-comments',
        ];

        if ($tables === []) {
            // Full dump: sertakan routines & triggers (rencana dasar).
            $command[] = '--routines';
            $command[] = '--triggers';
            $command[] = $database;
        } else {
            // Selective: hanya tabel yang diminta, tanpa routines/triggers
            // (routines/triggers bersifat global, bukan milik modul tertentu).
            $command[] = $database;
            array_push($command, ...$tables);
        }

        $process = new Process($command);
        $process->setTimeout(1800);

        $handle = fopen($sqlPath, 'wb');
        if ($handle === false) {
            throw new BackupException('Gagal membuat file dump sementara.');
        }

        try {
            $process->run(function (string $type, string $buffer) use ($handle) {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                }
            });
        } finally {
            fclose($handle);
        }

        if (!$process->isSuccessful()) {
            throw new BackupException('mysqldump gagal: '.trim($process->getErrorOutput()));
        }
    }

    /**
     * @param array<int,string> $modules
     * @param array<int,string> $tables
     */
    private function buildZip(string $zipPath, string $sqlPath, bool $isFull, array $modules, array $tables): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupException('Gagal membuat file ZIP.');
        }

        try {
            $zip->addFile($sqlPath, 'database.sql');

            $storageFilesCount = $isFull
                ? $this->addFullStorage($zip)
                : $this->addSelectiveStorage($zip, $modules);

            $manifest = [
                'version' => 1,
                'app' => config('app.name'),
                'generated_at' => now()->toIso8601String(),
                'is_full' => $isFull,
                'selection' => [
                    'modules' => $modules,
                ],
                'tables_included' => $isFull ? null : $tables,
                'storage_files_count' => $storageFilesCount,
            ];

            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } finally {
            $zip->close();
        }
    }

    /**
     * Full backup: zip mentah seluruh folder storage/app/private &
     * storage/app/public (cepat, tidak perlu jalan lewat DB).
     */
    private function addFullStorage(ZipArchive $zip): int
    {
        $count = 0;
        $count += $this->addDirectoryToZip($zip, storage_path('app/private'), 'storage/private');
        $count += $this->addDirectoryToZip($zip, storage_path('app/public'), 'storage/public');

        return $count;
    }

    private function addDirectoryToZip(ZipArchive $zip, string $sourceDir, string $zipPrefix): int
    {
        if (!is_dir($sourceDir)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $relative = ltrim(substr($file->getPathname(), strlen($sourceDir)), '/');
            $zip->addFile($file->getPathname(), $zipPrefix.'/'.$relative);
            $count++;
        }

        return $count;
    }

    /**
     * Backup parsial: hanya file yang path-nya tercatat di kolom row_column
     * milik tabel-tabel modul terpilih (bukan seluruh folder) — supaya file
     * yang dizip 1:1 dengan baris yang ikut di-dump.
     *
     * @param array<int,string> $modules
     */
    private function addSelectiveStorage(ZipArchive $zip, array $modules): int
    {
        $count = 0;

        foreach ($this->registry->storageForModules($modules) as $entry) {
            $paths = DB::table($entry['table'])
                ->whereNotNull($entry['column'])
                ->pluck($entry['column']);

            foreach ($paths as $relativePath) {
                if ($relativePath === '') {
                    continue;
                }

                $disk = $entry['disk'];
                $zipPrefix = $disk === 'public' ? 'storage/public' : 'storage/private';

                if (!Storage::disk($disk)->exists($relativePath)) {
                    continue;
                }

                $zip->addFile(Storage::disk($disk)->path($relativePath), $zipPrefix.'/'.$relativePath);
                $count++;
            }
        }

        return $count;
    }
}
