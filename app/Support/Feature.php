<?php

namespace App\Support;

class Feature
{
    public static function mode(): string
    {
        return config('app.mode', 'individual');
    }

    public static function isInstitution(): bool
    {
        return self::mode() === 'institution';
    }

    public static function isIndividual(): bool
    {
        return !self::isInstitution();
    }

    /**
     * Fitur prodi (multi-dosen & manajemen institusi) hanya aktif di mode institusi.
     * Fitur "inti" (logbook, revisi, sidang, penguji, workspace, registrasi mahasiswa)
     * tersedia di KEDUA mode.
     */
    public static function has(string $feature): bool
    {
        $institutionOnly = [
            'bulk_import',
            'koordinator',
            'laporan_institusi',
            'multi_dosen',
            'institution_admin',
        ];

        return in_array($feature, $institutionOnly, true)
            ? self::isInstitution()
            : true;
    }
}
