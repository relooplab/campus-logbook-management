<?php

namespace App\Services;

use App\Services\Backup\BackupException;
use App\Services\Backup\BackupModuleRegistry;
use App\Services\Backup\InstitutionClosureResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Membuat backup sistem: dump database (mysqldump) + file storage, dibungkus
 * jadi satu ZIP. Mendukung full backup (seluruh sistem), backup per-modul,
 * dan/atau backup per-institusi (bisa dikombinasikan) — lihat
 * config/backup_modules.php & config/backup_institution_scope.php.
 *
 * Restore mendukung SEMUA jenis backup di atas — restore selalu menerapkan
 * TEPAT scope yang tercatat di manifest.json ZIP-nya (lihat
 * SystemRestoreService); mempersempit scope dilakukan di sisi BACKUP, bukan
 * saat restore.
 */
class SystemBackupService
{
    /** Headroom disk yang diminta relatif terhadap estimasi ukuran backup. */
    private const DISK_HEADROOM_MULTIPLIER = 2.5;

    public function __construct(
        private readonly BackupModuleRegistry $registry,
        private readonly InstitutionClosureResolver $closureResolver,
    ) {
    }

    /**
     * @param array<int,string>|null $moduleKeys null/[] = semua modul
     * @param array<int,int>|null $institutionIds null/[] = tidak ada filter institusi (semua institusi)
     * @return string path absolut ke file ZIP hasil backup (di storage/framework/backup-tmp)
     *
     * @throws BackupException
     */
    public function create(?array $moduleKeys = null, ?array $institutionIds = null, bool $includeIndividual = false): string
    {
        $institutionIds = array_values(array_unique(array_map('intval', $institutionIds ?? [])));
        $hasModuleFilter = !empty($moduleKeys);
        $hasInstitutionFilter = $institutionIds !== [];
        $isFull = !$hasModuleFilter && !$hasInstitutionFilter;

        $modules = $hasModuleFilter
            ? $this->registry->resolveDependencyClosure($moduleKeys)
            : $this->registry->allModuleKeys();
        $tables = $isFull ? [] : $this->registry->tablesForModules($modules);

        $closure = $isFull
            ? ['scope' => [], 'closure_expansions' => [], 'skipped_conversations_outside_scope' => 0]
            : $this->closureResolver->resolve($tables, $institutionIds, $includeIndividual);

        $this->guardDiskSpace();

        $tmpRoot = storage_path('framework/backup-tmp');
        File::ensureDirectoryExists($tmpRoot);

        $workDir = $tmpRoot.'/'.(string) Str::uuid();
        File::ensureDirectoryExists($workDir);

        $credentialsFile = null;

        try {
            $credentialsFile = $this->makeCredentialsFile();
            $sqlPath = $workDir.'/database.sql';

            $this->dumpDatabase($sqlPath, $credentialsFile, $isFull ? [] : $closure['scope']);

            $zipPath = $tmpRoot.'/system-backup-'.now()->format('Ymd-His').'-'.Str::random(6).'.zip';
            $this->buildZip($zipPath, $sqlPath, $isFull, $modules, $closure['scope'], $institutionIds, $includeIndividual, $closure['closure_expansions'], $closure['skipped_conversations_outside_scope']);

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
     * @param array<string, array<int,int>|string> $scope kosong = full dump (seluruh database, tanpa filter tabel)
     */
    private function dumpDatabase(string $sqlPath, string $credentialsFile, array $scope): void
    {
        $database = config('database.connections.'.config('database.default').'.database');

        if ($scope === []) {
            // Full backup: satu mysqldump utuh, termasuk routines/triggers.
            $this->runMysqldump($sqlPath, false, [
                '--defaults-extra-file='.$credentialsFile,
                '--single-transaction',
                '--no-tablespaces',
                '--skip-comments',
                '--routines',
                '--triggers',
                $database,
            ]);

            return;
        }

        // Backup parsial (modul dan/atau institusi): SELALU data-only per tabel
        // (--no-create-info), TANPA statement CREATE TABLE sama sekali. Restore
        // parsial tidak pernah drop/recreate tabel — kalau CREATE TABLE ikut
        // didump, import akan gagal dengan "table already exists". mysqldump
        // --where hanya berlaku per-invocation, jadi satu proses per tabel.
        $first = true;
        foreach ($scope as $table => $ids) {
            if ($ids === []) {
                continue; // tidak ada baris dalam scope — lewati (hindari "IN ()" SQL invalid).
            }

            $args = [
                '--defaults-extra-file='.$credentialsFile,
                '--no-create-info',
                '--single-transaction',
                '--skip-comments',
            ];

            if ($ids !== '*') {
                $args[] = '--where=id IN ('.implode(',', array_map('intval', $ids)).')';
            }

            $args[] = $database;
            $args[] = $table;

            $this->runMysqldump($sqlPath, !$first, $args);
            $first = false;
        }
    }

    private function runMysqldump(string $sqlPath, bool $append, array $args): void
    {
        $process = new Process(array_merge(['mysqldump'], $args));
        $process->setTimeout(1800);

        $handle = fopen($sqlPath, $append ? 'ab' : 'wb');
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
     * @param array<string, array<int,int>|string> $scope
     * @param array<int,int> $institutionIds
     * @param array<int,array{table:string,user_id:int,reason:string}> $closureExpansions
     */
    private function buildZip(
        string $zipPath,
        string $sqlPath,
        bool $isFull,
        array $modules,
        array $scope,
        array $institutionIds,
        bool $includeIndividual,
        array $closureExpansions,
        int $skippedConversations
    ): void {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupException('Gagal membuat file ZIP.');
        }

        try {
            $zip->addFile($sqlPath, 'database.sql');

            $storageFilesCount = $isFull
                ? $this->addFullStorage($zip)
                : $this->addSelectiveStorage($zip, $modules, $scope);

            $rowIds = $isFull ? null : $this->resolveRowIds($scope);

            $manifest = [
                'version' => 2,
                'app' => config('app.name'),
                'generated_at' => now()->toIso8601String(),
                'is_full' => $isFull,
                'selection' => [
                    'modules' => $modules,
                    'institutions' => $institutionIds,
                    'include_individual' => $includeIndividual,
                ],
                'tables_included' => $isFull ? null : array_keys($scope),
                'row_ids' => $rowIds,
                'closure_expansions' => $closureExpansions,
                'skipped_conversations_outside_scope' => $skippedConversations,
                'storage_files_count' => $storageFilesCount,
            ];

            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } finally {
            $zip->close();
        }
    }

    /**
     * Ubah scope ('*' atau daftar id) jadi daftar id konkret untuk setiap
     * tabel — dibutuhkan restore nanti untuk id-exact delete+reinsert (lihat
     * ScopedRestoreExecutor). '*' tetap perlu di-query supaya manifest
     * menyimpan id sungguhan, bukan sekadar penanda.
     *
     * @param array<string, array<int,int>|string> $scope
     * @return array<string, array<int,int>>
     */
    private function resolveRowIds(array $scope): array
    {
        $rowIds = [];
        foreach ($scope as $table => $ids) {
            if ($ids === '*') {
                $rowIds[$table] = DB::table($table)->pluck('id')->map(fn ($v) => (int) $v)->all();
            } else {
                $rowIds[$table] = array_map('intval', $ids);
            }
        }

        return $rowIds;
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
     * milik BARIS yang ikut scope (bukan seluruh folder, bukan seluruh
     * tabel) — supaya file yang dizip 1:1 dengan baris yang ikut di-dump.
     *
     * @param array<int,string> $modules
     * @param array<string, array<int,int>|string> $scope
     */
    private function addSelectiveStorage(ZipArchive $zip, array $modules, array $scope): int
    {
        $count = 0;

        foreach ($this->registry->storageForModules($modules) as $entry) {
            $query = DB::table($entry['table'])->whereNotNull($entry['column']);

            $ids = $scope[$entry['table']] ?? '*';
            if ($ids !== '*') {
                if ($ids === []) {
                    continue;
                }
                $query->whereIn('id', $ids);
            }

            $paths = $query->pluck($entry['column']);

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
