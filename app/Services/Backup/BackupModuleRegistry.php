<?php

namespace App\Services\Backup;

use InvalidArgumentException;

/**
 * Baca config/backup_modules.php dan sediakan resolusi dependency closure +
 * urutan topologis (parent-dulu) untuk keperluan dump/restore.
 */
class BackupModuleRegistry
{
    /** @var array<string, array{label: string, description: string, tables: array<int,string>, depends_on: array<int,string>, storage: array<int,array{table:string,column:string,disk:string}>}> */
    private array $modules;

    public function __construct()
    {
        $this->modules = config('backup_modules.modules', []);
    }

    /**
     * @return array<int,string>
     */
    public function allModuleKeys(): array
    {
        return array_keys($this->modules);
    }

    /**
     * Definisi lengkap semua modul (label, description, depends_on, dst) —
     * dipakai untuk merender checklist modul di halaman admin.
     *
     * @return array<string, array{label: string, description: string, tables: array<int,string>, depends_on: array<int,string>, storage: array<int,array{table:string,column:string,disk:string}>}>
     */
    public function definitions(): array
    {
        return $this->modules;
    }

    public function label(string $moduleKey): string
    {
        $this->assertExists($moduleKey);

        return $this->modules[$moduleKey]['label'];
    }

    public function exists(string $moduleKey): bool
    {
        return array_key_exists($moduleKey, $this->modules);
    }

    /**
     * @return array<int,string>
     */
    public function tablesOf(string $moduleKey): array
    {
        $this->assertExists($moduleKey);

        return $this->modules[$moduleKey]['tables'];
    }

    /**
     * @return array<int,array{table:string,column:string,disk:string}>
     */
    public function storageOf(string $moduleKey): array
    {
        $this->assertExists($moduleKey);

        return $this->modules[$moduleKey]['storage'];
    }

    /**
     * Hitung transitive closure dependency dari daftar modul yang dipilih.
     * Graph dependency di config ini dangkal (bukan siklik), jadi cukup
     * traversal sederhana tanpa deteksi siklus.
     *
     * @param array<int,string> $selectedModuleKeys
     * @return array<int,string> daftar module key (termasuk yang dipilih + dependensinya), belum diurutkan topologis
     */
    public function resolveDependencyClosure(array $selectedModuleKeys): array
    {
        $closure = [];
        $queue = $selectedModuleKeys;

        while ($queue !== []) {
            $key = array_shift($queue);
            $this->assertExists($key);

            if (in_array($key, $closure, true)) {
                continue;
            }

            $closure[] = $key;

            foreach ($this->modules[$key]['depends_on'] as $dep) {
                if (!in_array($dep, $closure, true)) {
                    $queue[] = $dep;
                }
            }
        }

        return $closure;
    }

    /**
     * Urutkan modul secara topologis (parent/dependency dulu) — dipakai untuk
     * urutan dump maupun urutan INSERT saat restore.
     *
     * @param array<int,string> $moduleKeys modul yang mau diurutkan (biasanya hasil resolveDependencyClosure)
     * @return array<int,string>
     */
    public function topologicalOrder(array $moduleKeys): array
    {
        $visited = [];
        $ordered = [];

        $visit = function (string $key) use (&$visit, &$visited, &$ordered, $moduleKeys): void {
            if (isset($visited[$key])) {
                return;
            }
            $visited[$key] = true;

            foreach ($this->modules[$key]['depends_on'] as $dep) {
                if (in_array($dep, $moduleKeys, true)) {
                    $visit($dep);
                }
            }

            $ordered[] = $key;
        };

        foreach ($moduleKeys as $key) {
            $this->assertExists($key);
            $visit($key);
        }

        return $ordered;
    }

    /**
     * Daftar tabel (urut parent-dulu) untuk sekumpulan modul, sudah dalam
     * urutan topologis modul-nya. Tidak ada duplikasi tabel meski dua modul
     * kebetulan berbagi tabel (tidak terjadi di config saat ini, tapi dijaga).
     *
     * @param array<int,string> $moduleKeys
     * @return array<int,string>
     */
    public function tablesForModules(array $moduleKeys): array
    {
        $ordered = $this->topologicalOrder($moduleKeys);
        $tables = [];

        foreach ($ordered as $key) {
            foreach ($this->modules[$key]['tables'] as $table) {
                if (!in_array($table, $tables, true)) {
                    $tables[] = $table;
                }
            }
        }

        return $tables;
    }

    /**
     * Semua entri storage (table/column/disk) untuk sekumpulan modul.
     *
     * @param array<int,string> $moduleKeys
     * @return array<int,array{table:string,column:string,disk:string}>
     */
    public function storageForModules(array $moduleKeys): array
    {
        $ordered = $this->topologicalOrder($moduleKeys);
        $entries = [];

        foreach ($ordered as $key) {
            foreach ($this->modules[$key]['storage'] as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function assertExists(string $moduleKey): void
    {
        if (!$this->exists($moduleKey)) {
            throw new InvalidArgumentException("Modul backup tidak dikenal: {$moduleKey}");
        }
    }
}
