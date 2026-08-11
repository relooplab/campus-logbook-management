<?php

namespace App\Services;

use App\Models\AdminScope;
use App\Models\Department;
use App\Models\DirectorySubscription;
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
    // Status afiliasi pada pivot `user_university`.
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    /**
     * Cari perguruan tinggi berdasarkan nama (case-insensitive).
     */
    public function findUniversity(string $name): ?University
    {
        return University::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->first();
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
    public function findOrCreateUniversity(string $name): University
    {
        $existing = $this->findUniversity($name);
        if ($existing) {
            return $existing;
        }

        return University::create([
            'name' => trim($name),
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
        bool $replaceAll = false,
        string $status = self::STATUS_ACTIVE
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
                'status' => $status,
            ]);
            return;
        }

        $user->universities()->attach($university->id, [
            'faculty_id' => $faculty?->id,
            'department_id' => $department?->id,
            'study_program_id' => $studyProgram?->id,
            'is_primary' => $isPrimary,
            'status' => $status,
        ]);
    }

    /**
     * Set satu universitas sebagai primer (dan nonaktifkan yang lain).
     */
    public function setPrimaryUniversity(User $user, University $university): void
    {
        // Update pivot: set semua false dulu, lalu yang ini true.
        \DB::table('user_university')->where('user_id', $user->id)->update(['is_primary' => false]);
        $user->universities()->updateExistingPivot($university->id, ['is_primary' => true]);
    }
    /**
     * Ubah status sebuah afiliasi (pending/active/revoked) + jejak approver.
     */
    public function setAffiliationStatus(User $user, University $university, string $status, ?User $approver = null, ?string $note = null): void
    {
        $data = ['status' => $status];

        if ($approver) {
            $data['approved_by'] = $approver->id;
            $data['approved_at'] = now();
        }
        if ($note !== null) {
            $data['rejection_reason'] = $note;
        }

        $user->universities()->updateExistingPivot($university->id, $data);
    }

    /**
     * Setujui afiliasi dosen: jadikan `active`, tandai sebagai primer (karena
     * itu yang menurunkan akses Workspace Institusi), dan simpan jejak approver.
     */
    public function approveAffiliation(User $user, University $university, User $approver): void
    {
        $this->setAffiliationStatus($user, $university, self::STATUS_ACTIVE, $approver);

        // Jadikan afiliasi yg baru disetujui sebagai primer (akses mengikuti).
        $this->setPrimaryUniversity($user, $university);
    }

    /**
     * Cabut afiliasi (oleh dosen/revoke): status `revoked` + nonaktifkan primer.
     * Akses ke Workspace Institusi dari afiliasi tsb otomatis hilang.
     */
    public function revokeAffiliation(User $user, University $university): void
    {
        $this->setAffiliationStatus($user, $university, self::STATUS_REVOKED);
        $user->universities()->updateExistingPivot($university->id, ['is_primary' => false]);
    }

    /**
     * Tolak permintaan afiliasi (oleh admin): status `revoked` + alasan.
     */
    public function rejectAffiliation(User $user, University $university, User $rejector, string $reason): void
    {
        $this->setAffiliationStatus($user, $university, self::STATUS_REVOKED, $rejector, $reason);
        $user->universities()->updateExistingPivot($university->id, ['is_primary' => false]);
    }

    /**
     * Admin level paling rendah yang berwenang menyetujui dosen masuk ke sebuah
     * prodi (leaf direktori). Prioritas cakupan admin: prodi → departemen →
     * fakultas (paling spesifik dulu). Jika tidak ada admin ber-scope yang
     * mencakup node tsb, fallback ke admin institusi penuh (tanpa scope) dan
     * system admin.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function lowestLevelAdminsForStudyProgram(StudyProgram $prodi): \Illuminate\Support\Collection
    {
        $deptId = $prodi->department_id;
        $facultyId = $prodi->department?->faculty_id;

        $candidates = [['type' => AdminScope::SCOPE_STUDY_PROGRAM, 'id' => $prodi->id]];
        if ($deptId) {
            $candidates[] = ['type' => AdminScope::SCOPE_DEPARTMENT, 'id' => $deptId];
        }
        if ($facultyId) {
            $candidates[] = ['type' => AdminScope::SCOPE_FACULTY, 'id' => $facultyId];
        }

        // Cakupan admin yang paling spesifik dulu: prodi → dept → fakultas.
        foreach ($candidates as $c) {
            $adminIds = AdminScope::where('status', AdminScope::STATUS_ACTIVE)
                ->where('scope_type', $c['type'])
                ->where('scope_id', $c['id'])
                ->distinct()
                ->pluck('user_id');

            if ($adminIds->isNotEmpty()) {
                return User::whereIn('id', $adminIds)
                    ->get();
            }
        }

        // Fallback: admin institusi penuh (tanpa admin_scope aktif) + system admin.
        return User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'system_admin']))
            ->whereDoesntHave('adminScopes')
            ->get();
    }

}