<?php

namespace App\Services;

use App\Models\MahasiswaTa;
use App\Models\ProgramNamingConfig;
use Illuminate\Support\Facades\Cache;

/**
 * Resolusi penamaan program (TA/KP) & label fase.
 *
 * Hierarki (paling spesifik menang):
 *   1. Konfigurasi prodi (study_program) mahasiswa
 *   2. Konfigurasi departemen mahasiswa
 *   3. Default dari konstanta MahasiswaTa::FASES / FASES_KP
 */
class ProgramNamingService
{
    /**
     * Ambil konfigurasi naming untuk MahasiswaTa tertentu.
     * Prioritas: prodi -> departemen -> null.
     */
    public function resolveConfig(MahasiswaTa $ta): ?ProgramNamingConfig
    {
        $mahasiswa = $ta->mahasiswa;
        if (!$mahasiswa) {
            return null;
        }

        $affiliation = $mahasiswa->universities()
            ->orderByDesc('user_university.is_primary')
            ->first();

        if (!$affiliation) {
            return null;
        }

        $institutionId = $ta->institution_id;

        // 1. Prodi.
        if ($affiliation->pivot->study_program_id) {
            $config = $this->configFor(
                $institutionId,
                ProgramNamingConfig::SCOPE_STUDY_PROGRAM,
                (int) $affiliation->pivot->study_program_id,
                $ta->jenis
            );
            if ($config) {
                return $config;
            }
        }

        // 2. Departemen.
        if ($affiliation->pivot->department_id) {
            $config = $this->configFor(
                $institutionId,
                ProgramNamingConfig::SCOPE_DEPARTMENT,
                (int) $affiliation->pivot->department_id,
                $ta->jenis
            );
            if ($config) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Ambil konfigurasi per scope+jenis, di-cache.
     */
    public function configFor(?int $institutionId, string $scopeType, int $scopeId, string $jenis): ?ProgramNamingConfig
    {
        if (!$institutionId) {
            return null;
        }

        return Cache::remember(
            "program-naming:{$institutionId}:{$scopeType}:{$scopeId}:{$jenis}",
            now()->addDay(),
            fn () => ProgramNamingConfig::where('institution_id', $institutionId)
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->where('jenis', $jenis)
                ->first()
        );
    }

    /**
     * Flush cache konfigurasi naming untuk scope+jenis tertentu.
     */
    public function flush(?int $institutionId, string $scopeType, int $scopeId, string $jenis): void
    {
        if ($institutionId) {
            Cache::forget("program-naming:{$institutionId}:{$scopeType}:{$scopeId}:{$jenis}");
        }
    }

    /**
     * Daftar key fase untuk jenis program (tetap dari konstanta).
     */
    public function faseKeys(MahasiswaTa $ta): array
    {
        return array_keys($ta->isKp() ? MahasiswaTa::FASES_KP : MahasiswaTa::FASES);
    }

    /**
     * Daftar label fase (kustom atau default) untuk MahasiswaTa.
     */
    public function faseLabels(MahasiswaTa $ta): array
    {
        $defaults = $ta->isKp() ? MahasiswaTa::FASES_KP : MahasiswaTa::FASES;
        $config = $this->resolveConfig($ta);

        if (!$config || empty($config->fase_labels)) {
            return $defaults;
        }

        // Gabungkan: label kustom menimpa default per key.
        return array_merge($defaults, array_filter($config->fase_labels, fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * Label fase saat ini untuk MahasiswaTa.
     */
    public function faseLabel(MahasiswaTa $ta): string
    {
        $labels = $this->faseLabels($ta);

        return $labels[$ta->fase] ?? $ta->fase;
    }

    /**
     * Label program (TA/KP) — kustom atau default.
     */
    public function jenisLabel(MahasiswaTa $ta): string
    {
        $config = $this->resolveConfig($ta);

        if ($config && $config->program_label) {
            return $config->program_label;
        }

        return $ta->isKp() ? 'KP' : 'TA';
    }

    /**
     * Label fase untuk jenis program tertentu (tanpa instance MahasiswaTa).
     * Dipakai di form pendaftaran/approval yang belum punya MahasiswaTa.
     */
    public function faseLabelsFor(?int $institutionId, string $jenis, ?int $studyProgramId = null, ?int $departmentId = null): array
    {
        $defaults = $jenis === MahasiswaTa::JENIS_KP ? MahasiswaTa::FASES_KP : MahasiswaTa::FASES;

        if (!$institutionId) {
            return $defaults;
        }

        // Prodi dulu, lalu departemen.
        if ($studyProgramId) {
            $config = $this->configFor($institutionId, ProgramNamingConfig::SCOPE_STUDY_PROGRAM, $studyProgramId, $jenis);
            if ($config && !empty($config->fase_labels)) {
                return array_merge($defaults, array_filter($config->fase_labels, fn ($v) => $v !== null && $v !== ''));
            }
        }

        if ($departmentId) {
            $config = $this->configFor($institutionId, ProgramNamingConfig::SCOPE_DEPARTMENT, $departmentId, $jenis);
            if ($config && !empty($config->fase_labels)) {
                return array_merge($defaults, array_filter($config->fase_labels, fn ($v) => $v !== null && $v !== ''));
            }
        }

        return $defaults;
    }

    /**
     * Label program untuk jenis tertentu (tanpa instance MahasiswaTa).
     */
    public function jenisLabelFor(?int $institutionId, string $jenis, ?int $studyProgramId = null, ?int $departmentId = null): string
    {
        if (!$institutionId) {
            return $jenis === MahasiswaTa::JENIS_KP ? 'KP' : 'TA';
        }

        if ($studyProgramId) {
            $config = $this->configFor($institutionId, ProgramNamingConfig::SCOPE_STUDY_PROGRAM, $studyProgramId, $jenis);
            if ($config && $config->program_label) {
                return $config->program_label;
            }
        }

        if ($departmentId) {
            $config = $this->configFor($institutionId, ProgramNamingConfig::SCOPE_DEPARTMENT, $departmentId, $jenis);
            if ($config && $config->program_label) {
                return $config->program_label;
            }
        }

        return $jenis === MahasiswaTa::JENIS_KP ? 'KP' : 'TA';
    }
}