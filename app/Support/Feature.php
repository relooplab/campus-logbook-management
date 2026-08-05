<?php

namespace App\Support;

use App\Models\Department;
use App\Models\DirectorySubscription;
use App\Models\Faculty;
use App\Models\Plan;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use App\Models\UserPlanOverride;
use App\Models\UserStorageAddon;

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
     *
     * Fitur berbasis paket (export/import) dicek berdasarkan plan user + override admin.
     */
    public static function has(string $feature, ?User $user = null): bool
    {
        $institutionOnly = [
            'bulk_import',
            'laporan_institusi',
            'multi_dosen',
            'institution_admin',
        ];

        if (in_array($feature, $institutionOnly, true)) {
            return self::isInstitution();
        }

        // Fitur berbasis paket: export & import.
        // SEMENTARA: semua user bisa (paket belum diterapkan). TODO: aktifkan
        // gate saat paket berbayar diterapkan -> return self::userHasFeature($user, $feature);
        if (in_array($feature, ['export', 'import'], true)) {
            return true;
        }

        // Permission-based granularity: jika fitur punya permission terkait,
        // cek apakah user punya permission tersebut (langsung atau via role).
        if ($user) {
            $permissionMap = [
                'logbook.create' => 'logbook.create',
                'logbook.review' => 'logbook.review',
                'workspace.upload' => 'workspace.upload',
                'workspace.delete' => 'workspace.delete',
                'workspace.manage-others' => 'workspace.manage-others',
                'seminar.submit' => 'seminar.submit',
                'seminar.review' => 'seminar.review',
                'finalization.submit' => 'finalization.submit',
                'finalization.approve' => 'finalization.approve',
                'sidang.record' => 'sidang.record',
                'announcement.create' => 'announcement.create',
                'chat.send' => 'chat.send',
                'storage.manage' => 'storage.manage',
                'groups.create' => 'groups.create',
                'groups.invite' => 'groups.invite',
                'approval.manage' => 'approval.manage',
                'admin.users' => 'admin.users',
                'admin.tas' => 'admin.tas',
                'admin.sidangs' => 'admin.sidangs',
                'admin.institution' => 'admin.institution',
                'admin.bulk-review' => 'admin.bulk-review',
            ];

            if (isset($permissionMap[$feature])) {
                return $user->hasPermissionTo($permissionMap[$feature]);
            }
        }

        return true;
    }

    /**
     * Cek apakah user memiliki fitur (export/import) berdasarkan:
     * 1. Override admin (jika ada) -> menang.
     * 2. Plan aktif user -> fitur plan.
     * 3. Default: false (free).
     */
    private static function userHasFeature(?User $user, string $feature): bool
    {
        if (!$user) {
            return false;
        }

        // 1. Override admin.
        $override = UserPlanOverride::where('user_id', $user->id)->first();
        if ($override) {
            $overrideValue = $feature === 'export' ? $override->allow_export : $override->allow_import;
            if ($overrideValue !== null) {
                return (bool) $overrideValue;
            }
        }

        // 2. Plan aktif user.
        $plan = $user->activePlan();
        if ($plan) {
            return (bool) $plan->feature($feature, false);
        }

        // 3. Default.
        return false;
    }

    /**
     * Batas penyimpanan (MB) untuk user:
     * 1. Override admin — menang mutlak (TIDAK BERUBAH).
     * 2. Base: total langganan direktori (institusi) menang atas plan individual,
     *    TIDAK dijumlah dengan plan individual.
     * 3. Top-up individual — SELALU additive di atas base manapun.
     */
    public static function storageLimitMb(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        // 1. Override admin — tetap menang mutlak.
        $override = UserPlanOverride::where('user_id', $user->id)->first();
        if ($override && $override->storage_limit_mb !== null) {
            return (int) $override->storage_limit_mb;
        }

        // 2. Base: langganan direktori (institusi) menang atas plan individual.
        $directoryBase = self::directoryStorageLimitMb($user);
        if ($directoryBase > 0) {
            $base = $directoryBase;
        } else {
            $plan = $user->activePlan();
            $base = $plan
                ? $plan->storageLimitMb()
                : (Plan::where('name', 'free')->where('is_active', true)->first()?->storageLimitMb() ?? 0);
        }

        // 3. Top-up individual — SELALU additive di atas base manapun.
        return $base + self::storageAddonMb($user);
    }

    /**
     * Total kuota dari SEMUA langganan direktori yang berlaku untuk dosen ini,
     * dijumlah lintas cabang berbeda (dedup kalau resolve ke node yang sama).
     * HANYA jalan di mode institusi.
     */
    public static function directoryStorageLimitMb(?User $user): int
    {
        if (!self::isInstitution() || !$user) {
            return 0;
        }

        $affiliations = $user->universities()->get();
        $resolvedSubscriptionIds = [];
        $total = 0;

        foreach ($affiliations as $aff) {
            // Urut dari paling spesifik ke paling umum. Karena dobel-cover 1 rantai
            // sudah divalidasi tidak boleh terjadi saat assign, cukup ambil yang
            // pertama ketemu aktif di rantai ini.
            $chain = [
                ['type' => DirectorySubscription::SCOPE_STUDY_PROGRAM, 'id' => $aff->pivot->study_program_id],
                ['type' => DirectorySubscription::SCOPE_DEPARTMENT, 'id' => $aff->pivot->department_id],
                ['type' => DirectorySubscription::SCOPE_FACULTY, 'id' => $aff->pivot->faculty_id],
                ['type' => DirectorySubscription::SCOPE_UNIVERSITY, 'id' => $aff->id],
            ];

            foreach ($chain as $node) {
                if (!$node['id']) {
                    continue;
                }

                $sub = DirectorySubscription::activeFor($node['type'], (int) $node['id']);

                if ($sub) {
                    // Dedup: kalau 2 afiliasi resolve ke subscription yang sama, jangan dobel hitung.
                    if (!in_array($sub->id, $resolvedSubscriptionIds, true)) {
                        $resolvedSubscriptionIds[] = $sub->id;
                        $total += $sub->plan->storageLimitMb();
                    }
                    break; // sudah ketemu di rantai ini, tidak perlu naik lebih tinggi
                }
            }
        }

        return $total;
    }

    /**
     * Total top-up storage individual (MB) yang aktif untuk user.
     * Selalu additive di atas base manapun.
     */
    public static function storageAddonMb(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        return (int) UserStorageAddon::where('user_id', $user->id)
            ->where('status', UserStorageAddon::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->sum('storage_mb');
    }

    /**
     * Cek apakah node direktori tertentu (atau leluhurnya) sudah tercover
     * langganan aktif. Dipakai untuk validasi assign (no-overlap) DAN untuk
     * gate pembuatan akun admin.
     */
    public static function directorySubscriptionActive(string $scopeType, int $scopeId): bool
    {
        if (!self::isInstitution()) {
            return false;
        }

        // Walk up dari node ini ke leluhurnya, true kalau salah satu ketemu aktif.
        foreach (self::directoryChain($scopeType, $scopeId) as $node) {
            if (DirectorySubscription::activeFor($node['type'], $node['id'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek apakah institusi punya minimal 1 langganan direktori aktif.
     * Dipakai sebagai gate pembuatan akun admin institusi-penuh (tanpa admin_scopes).
     */
    public static function institutionHasActiveDirectorySubscription(int $institutionId): bool
    {
        if (!self::isInstitution()) {
            return false;
        }

        // Ambil semua user di institusi ini yang punya afiliasi universitas.
        $userIds = \App\Models\User::where('institution_id', $institutionId)
            ->whereHas('universities')
            ->pluck('id');

        if ($userIds->isEmpty()) {
            return false;
        }

        // Ambil semua afiliasi user di institusi ini.
        $affiliations = \DB::table('user_university')
            ->whereIn('user_id', $userIds)
            ->get();

        $resolvedSubscriptionIds = [];

        foreach ($affiliations as $aff) {
            $chain = [
                ['type' => DirectorySubscription::SCOPE_STUDY_PROGRAM, 'id' => $aff->study_program_id],
                ['type' => DirectorySubscription::SCOPE_DEPARTMENT, 'id' => $aff->department_id],
                ['type' => DirectorySubscription::SCOPE_FACULTY, 'id' => $aff->faculty_id],
                ['type' => DirectorySubscription::SCOPE_UNIVERSITY, 'id' => $aff->university_id],
            ];

            foreach ($chain as $node) {
                if (!$node['id']) {
                    continue;
                }

                $sub = DirectorySubscription::activeFor($node['type'], (int) $node['id']);
                if ($sub) {
                    return true;
                }
                // JANGAN break di sini — lanjut ke leluhur yang lebih tinggi
                // (mis. prodi tidak berlangganan, tapi fakultas/univ mungkin aktif).
            }
        }

        return false;
    }

    /**
     * Rantai leluhur dari node direktori (dari node itu sendiri ke paling umum).
     * Contoh: study_program(5) -> department(3) -> faculty(2) -> university(1).
     */
    public static function directoryChain(string $scopeType, int $scopeId): array
    {
        $chain = [];

        switch ($scopeType) {
            case DirectorySubscription::SCOPE_STUDY_PROGRAM:
                $prodi = StudyProgram::with('department.faculty.university')->find($scopeId);
                if (!$prodi) {
                    return [];
                }
                $chain[] = ['type' => DirectorySubscription::SCOPE_STUDY_PROGRAM, 'id' => $prodi->id];
                if ($prodi->department) {
                    $chain[] = ['type' => DirectorySubscription::SCOPE_DEPARTMENT, 'id' => $prodi->department->id];
                    if ($prodi->department->faculty) {
                        $chain[] = ['type' => DirectorySubscription::SCOPE_FACULTY, 'id' => $prodi->department->faculty->id];
                        if ($prodi->department->faculty->university) {
                            $chain[] = ['type' => DirectorySubscription::SCOPE_UNIVERSITY, 'id' => $prodi->department->faculty->university->id];
                        }
                    }
                }
                break;

            case DirectorySubscription::SCOPE_DEPARTMENT:
                $dept = Department::with('faculty.university')->find($scopeId);
                if (!$dept) {
                    return [];
                }
                $chain[] = ['type' => DirectorySubscription::SCOPE_DEPARTMENT, 'id' => $dept->id];
                if ($dept->faculty) {
                    $chain[] = ['type' => DirectorySubscription::SCOPE_FACULTY, 'id' => $dept->faculty->id];
                    if ($dept->faculty->university) {
                        $chain[] = ['type' => DirectorySubscription::SCOPE_UNIVERSITY, 'id' => $dept->faculty->university->id];
                    }
                }
                break;

            case DirectorySubscription::SCOPE_FACULTY:
                $faculty = Faculty::with('university')->find($scopeId);
                if (!$faculty) {
                    return [];
                }
                $chain[] = ['type' => DirectorySubscription::SCOPE_FACULTY, 'id' => $faculty->id];
                if ($faculty->university) {
                    $chain[] = ['type' => DirectorySubscription::SCOPE_UNIVERSITY, 'id' => $faculty->university->id];
                }
                break;

            case DirectorySubscription::SCOPE_UNIVERSITY:
                $univ = University::find($scopeId);
                if (!$univ) {
                    return [];
                }
                $chain[] = ['type' => DirectorySubscription::SCOPE_UNIVERSITY, 'id' => $univ->id];
                break;
        }

        return $chain;
    }

    /**
     * Validasi no-overlap saat assign directory_subscriptions.
     * Tolak kalau leluhur ATAU turunan node ini sudah punya langganan aktif.
     *
     * @return string|null Pesan error, atau null kalau valid.
     */
    public static function validateDirectorySubscriptionNoOverlap(string $scopeType, int $scopeId): ?string
    {
        if (!self::isInstitution()) {
            return null;
        }

        // 1. Ke atas (leluhur): node ini atau leluhurnya sudah tercover?
        $chain = self::directoryChain($scopeType, $scopeId);
        // Skip node itu sendiri (kita sedang assign ke node ini).
        $ancestors = array_slice($chain, 1);
        foreach ($ancestors as $node) {
            if (DirectorySubscription::activeFor($node['type'], $node['id'])) {
                return 'Node induk sudah berlangganan, node ini otomatis sudah tercover.';
            }
        }

        // 2. Ke bawah (turunan): cek apakah ada turunan yang sudah berlangganan sendiri.
        $descendantHasSubscription = self::directoryDescendantHasActiveSubscription($scopeType, $scopeId);
        if ($descendantHasSubscription) {
            return 'Ada turunan yang sudah berlangganan sendiri, nonaktifkan dulu sebelum assign di level ini.';
        }

        return null;
    }

    /**
     * Cek apakah ada turunan node ini yang sudah punya langganan aktif.
     */
    private static function directoryDescendantHasActiveSubscription(string $scopeType, int $scopeId): bool
    {
        $activeSubs = DirectorySubscription::where('status', DirectorySubscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->get();

        foreach ($activeSubs as $sub) {
            // Cek apakah scope subscription ini adalah turunan dari node target.
            if (self::isDescendantOf($sub->scope_type, $sub->scope_id, $scopeType, $scopeId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apakah node (childType, childId) adalah turunan dari (parentType, parentId)?
     */
    private static function isDescendantOf(string $childType, int $childId, string $parentType, int $parentId): bool
    {
        if ($childType === $parentType && $childId === $parentId) {
            return false; // bukan turunan, node yang sama
        }

        $chain = self::directoryChain($childType, $childId);

        foreach ($chain as $node) {
            if ($node['type'] === $parentType && $node['id'] === $parentId) {
                return true;
            }
        }

        return false;
    }
}
