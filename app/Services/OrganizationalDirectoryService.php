<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;

/**
 * Membantu logika "pilih atau buat" pada direktori organisasi (4 level)
 * serta menghubungkan user ke universitas (multi-universitas).
 *
 * Deduplikasi: mencari berdasarkan nama (case-insensitive) sebelum membuat
 * record baru, sehingga nama perguruan tinggi tidak muncul dua kali.
 */
class OrganizationalDirectoryService
{
    /**
     * Cari perguruan tinggi berdasarkan nama (case-insensitive) atau NPSN.
     */
    public function findUniversity(string $name, ?string $npsn = null): ?University
    {
        $query = University::query();

        if ($npsn) {
            $query->where('npsn', $npsn);
        }

        return $query->orWhereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->first();
    }

    /**
     * Ambil fakultas berdasarkan nama (case-insensitive) di dalam universitas.
     */
    public function findFaculty(University $university, string $name): ?Faculty
    {
        return $university->faculties()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->first();
    }

    /**
     * Ambil departemen berdasarkan nama (case-insensitive) di dalam fakultas.
     */
    public function findDepartment(Faculty $faculty, string $name): ?Department
    {
        return $faculty->departments()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->first();
    }

    /**
     * Ambil prodi berdasarkan nama (case-insensitive) di dalam departemen.
     */
    public function findStudyProgram(Department $department, string $name): ?StudyProgram
    {
        return $department->studyPrograms()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->first();
    }

    /**
     * Pilih atau buat perguruan tinggi (dedup).
     */
    public function findOrCreateUniversity(string $name, ?string $npsn = null): University
    {
        $existing = $this->findUniversity($name, $npsn);
        if ($existing) {
            return $existing;
        }

        return University::create([
            'name' => trim($name),
            'npsn' => $npsn ?: null,
        ]);
    }

    /**
     * Pilih atau buat fakultas (dedup di dalam universitas).
     */
    public function findOrCreateFaculty(University $university, string $name): Faculty
    {
        $existing = $this->findFaculty($university, $name);
        if ($existing) {
            return $existing;
        }

        return $university->faculties()->create(['name' => trim($name)]);
    }

    /**
     * Pilih atau buat departemen (dedup di dalam fakultas).
     */
    public function findOrCreateDepartment(Faculty $faculty, string $name): Department
    {
        $existing = $this->findDepartment($faculty, $name);
        if ($existing) {
            return $existing;
        }

        return $faculty->departments()->create(['name' => trim($name)]);
    }

    /**
     * Pilih atau buat prodi (dedup di dalam departemen).
     */
    public function findOrCreateStudyProgram(Department $department, string $name, ?string $code = null): StudyProgram
    {
        $existing = $this->findStudyProgram($department, $name);
        if ($existing) {
            return $existing;
        }

        return $department->studyPrograms()->create([
            'name' => trim($name),
            'code' => $code ?: null,
        ]);
    }

    /**
     * Hubungkan user ke universitas (multi-universitas). Jika sudah ada,
     * perbarui fakultas/departemen/prodi. `is_primary=true` jika belum ada
     * universitas primer lain.
     *
     * @param bool $replaceAll Jika true, hapus SEMUA afiliasi user terlebih
     *                         dahulu sebelum attach yang baru. Dipakai untuk
     *                         mahasiswa yang hanya boleh punya 1 afiliasi.
     */
    public function attachUserToUniversity(
        User $user,
        University $university,
        ?Faculty $faculty = null,
        ?Department $department = null,
        ?StudyProgram $studyProgram = null,
        bool $isPrimary = false,
        bool $replaceAll = false
    ): void {
        if ($replaceAll) {
            $user->universities()->detach();
        }

        $exists = $user->universities()->where('university_id', $university->id)->exists();

        if ($exists) {
            $user->universities()->updateExistingPivot($university->id, [
                'faculty_id' => $faculty?->id,
                'department_id' => $department?->id,
                'study_program_id' => $studyProgram?->id,
                'is_primary' => $isPrimary,
            ]);
            return;
        }

        $user->universities()->attach($university->id, [
            'faculty_id' => $faculty?->id,
            'department_id' => $department?->id,
            'study_program_id' => $studyProgram?->id,
            'is_primary' => $isPrimary,
        ]);
    }

    /**
     * Set satu universitas sebagai primer (dan nonaktifkan yang lain).
     */
    public function setPrimaryUniversity(User $user, University $university): void
    {
        $user->universities()->updateExistingPivot($university->id, ['is_primary' => true]);
        $user->universities()->where('university_id', '!=', $university->id)
            ->update(['is_primary' => false]);
    }
}