<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;

/**
 * Verifikasi integritas FK setelah restore PARSIAL (id-exact replace). Wajib
 * dijalankan setiap kali karena closure institusi/modul saat backup diambil
 * bisa berbeda dari kondisi live saat restore dijalankan — mis. user yang
 * direferensikan sebuah baris sudah dihapus dari sistem sejak backup
 * diambil. Restore full TIDAK perlu ini (wipe total selalu konsisten
 * terhadap dirinya sendiri).
 *
 * Pola query per entri: `column IS NOT NULL AND column NOT IN (SELECT id
 * FROM references)` — sumber kebenaran FK ada di config/backup_fk_checks.php
 * (bukan digenerate otomatis dari skema DB, supaya daftarnya eksplisit &
 * bisa direview).
 */
class BackupIntegrityChecker
{
    /** Contoh id orphan yang disertakan per temuan, buat memudahkan investigasi manual. */
    private const SAMPLE_LIMIT = 5;

    /**
     * @param array<int,string> $tablesRestored tabel yang baru saja disentuh restore parsial
     * @return array<int,array{table:string,column:string,references:string,orphan_count:int,sample_ids:array<int,int>}>
     *         array kosong = bersih, tidak ada orphan
     */
    public function verify(array $tablesRestored): array
    {
        $findings = [];

        foreach (config('backup_fk_checks.checks', []) as $check) {
            if (!in_array($check['table'], $tablesRestored, true)) {
                continue;
            }

            $baseQuery = fn () => DB::table($check['table'])
                ->whereNotNull($check['column'])
                ->whereNotIn($check['column'], function ($q) use ($check) {
                    $q->select('id')->from($check['references']);
                });

            $orphanCount = $baseQuery()->count();
            if ($orphanCount === 0) {
                continue;
            }

            $findings[] = [
                'table' => $check['table'],
                'column' => $check['column'],
                'references' => $check['references'],
                'orphan_count' => $orphanCount,
                'sample_ids' => $baseQuery()->limit(self::SAMPLE_LIMIT)->pluck('id')->map(fn ($v) => (int) $v)->all(),
            ];
        }

        return $findings;
    }
}
