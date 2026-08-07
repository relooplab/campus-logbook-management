<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Restore backup PARSIAL (modul dan/atau institusi) — id-exact replace,
 * BUKAN wipe total. Manifest menyimpan `row_ids` persis (primary key tiap
 * baris dalam paket backup) — restore DELETE hanya id yang ADA di manifest,
 * lalu INSERT ulang dari data dump. Baris yang ada di DB sekarang tapi TIDAK
 * ada di backup (dibuat setelah backup diambil, atau di luar scope) TIDAK
 * tersentuh sama sekali. Tabel yang tidak ada di `tables_included` manifest
 * juga sama sekali tidak disentuh.
 *
 * DELETE dan INSERT dijalankan dalam SATU sesi `mysql` dengan
 * FOREIGN_KEY_CHECKS=0 — bukan cuma untuk melewati validasi FK saat INSERT,
 * tapi juga supaya MySQL TIDAK men-cascade-delete baris di tabel lain yang
 * mereferensikan baris yang kita DELETE (banyak FK di aplikasi ini
 * cascadeOnDelete) — cascade semacam itu bisa menghapus data di luar scope
 * restore ini kalau FK checks aktif.
 */
class ScopedRestoreExecutor
{
    public function __construct(
        private readonly BackupModuleRegistry $registry,
    ) {
    }

    /**
     * @param array<string,mixed> $manifest
     */
    public function restore(string $extractDir, array $manifest, string $credentialsFile): void
    {
        $tables = $manifest['tables_included'] ?? [];
        $rowIds = $manifest['row_ids'] ?? [];

        $this->replaceScopedData($extractDir.'/database.sql', $credentialsFile, $tables, $rowIds);
        $this->swapScopedStorage($extractDir, $manifest);
    }

    /**
     * @param array<int,string> $tables urutan parent-dulu (sesuai urutan topologis saat backup)
     * @param array<string,array<int,int>> $rowIds
     */
    private function replaceScopedData(string $dataSqlPath, string $credentialsFile, array $tables, array $rowIds): void
    {
        $deleteStatements = [];
        foreach (array_reverse($tables) as $table) {
            $ids = $rowIds[$table] ?? [];
            if ($ids === []) {
                continue;
            }
            $escapedTable = str_replace('`', '``', $table);
            $idList = implode(',', array_map('intval', $ids));
            $deleteStatements[] = "DELETE FROM `{$escapedTable}` WHERE `id` IN ({$idList});";
        }

        $database = config('database.connections.'.config('database.default').'.database');

        $process = new Process(['mysql', '--defaults-extra-file='.$credentialsFile, $database]);
        $process->setTimeout(1800);

        $process->setInput((function () use ($deleteStatements, $dataSqlPath) {
            yield "SET FOREIGN_KEY_CHECKS=0;\n";

            foreach ($deleteStatements as $statement) {
                yield $statement."\n";
            }

            $handle = fopen($dataSqlPath, 'rb');
            if ($handle !== false) {
                while (!feof($handle)) {
                    yield fread($handle, 8192);
                }
                fclose($handle);
            }

            yield "\nSET FOREIGN_KEY_CHECKS=1;\n";
        })());

        $process->run();

        if (!$process->isSuccessful()) {
            throw new RestoreException(
                'Restore parsial gagal di tengah jalan: '.trim($process->getErrorOutput())
                .' Safety-backup otomatis tersedia untuk recovery — lihat log audit.'
            );
        }
    }

    /**
     * Hanya ganti file yang path-nya terhubung ke baris yang baru direplace
     * (bukan seluruh folder) — path BARU (hasil restore) di-query ulang dari
     * DB setelah replaceScopedData() selesai, lalu file yang bersangkutan
     * dicopy dari hasil extract ZIP ke disk sungguhan.
     *
     * @param array<string,mixed> $manifest
     */
    private function swapScopedStorage(string $extractDir, array $manifest): void
    {
        $rowIds = $manifest['row_ids'] ?? [];
        $tables = $manifest['tables_included'] ?? [];
        $modules = $manifest['selection']['modules'] ?? [];

        foreach ($this->registry->storageForModules($modules) as $entry) {
            if (!in_array($entry['table'], $tables, true)) {
                continue;
            }

            $ids = $rowIds[$entry['table']] ?? [];
            if ($ids === []) {
                continue;
            }

            $newPaths = DB::table($entry['table'])
                ->whereIn('id', $ids)
                ->whereNotNull($entry['column'])
                ->pluck($entry['column']);

            $disk = $entry['disk'];
            $zipPrefix = $disk === 'public' ? 'storage/public' : 'storage/private';

            foreach ($newPaths as $relativePath) {
                if ($relativePath === '') {
                    continue;
                }

                $sourceFile = $extractDir.'/'.$zipPrefix.'/'.$relativePath;
                if (!is_file($sourceFile)) {
                    continue; // File tidak ada di ZIP (mis. sudah terhapus sejak backup diambil).
                }

                $destPath = Storage::disk($disk)->path($relativePath);
                File::ensureDirectoryExists(dirname($destPath));
                copy($sourceFile, $destPath);
            }
        }
    }
}
