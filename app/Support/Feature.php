<?php

namespace App\Support;

use App\Models\Department;
use App\Models\DirectorySubscription;
use App\Models\Faculty;
use App\Models\MahasiswaTa;
use App\Models\Plan;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use App\Models\UserPlanOverride;
use App\Models\UserStorageAddon;

class Feature
{
    /** Kuota penyimpanan sementara (MB) untuk mahasiswa fase pending (menunggu persetujuan dosen). */
    public const PENDING_STUDENT_STORAGE_LIMIT_MB = 100;

    /**
     * Default kuota penyimpanan DOSEN (MB) bila TIDAK ADA sumber kuota lain
     * yang terdefinisi (override admin, pool institusi, plan individual, maupun
     * free plan). Disamakan dengan kuota free plan: 3 GB = 3072 MB.
     * Berlaku KHUSUS untuk role dosen; selain dosen tetap 0.
     */
    public const DEFAULT_STORAGE_LIMIT_MB = 3072;

    public static function mode(): string
    {
        return config('app.mode', 'saas');
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
     * Apakah SMTP sungguhan (produksi) terkonfigurasi secara efektif.
     * Mengecualikan sink pengembangan (Mailpit / localhost) agar verifikasi
     * email tidak otomatis aktif di lingkungan dev.
     */
    public static function smtpConfigured(): bool
    {
        if (config('mail.default') !== 'smtp') {
            return false;
        }

        $host = strtolower((string) config('mail.mailers.smtp.host', ''));
        if ($host === '') {
            return false;
        }

        $sinks = ['mailpit', '127.0.0.1', 'localhost', '0.0.0.0'];
        if (in_array($host, $sinks, true) || str_contains($host, 'mailpit')) {
            return false;
        }

        return true;
    }

    /**
     * Fitur prodi (multi-dosen & manajemen institusi) tersedia untuk SEMUA user.
     * Gate dilakukan per-user berdasarkan institution_id, bukan APP_MODE global.
     *
     * Fitur "inti" (logbook, revisi, sidang, penguji, workspace, registrasi mahasiswa)
     * tersedia untuk semua.
     *
     * Fitur berbasis paket (export/import) dicek berdasarkan plan user + override admin.
     */
    public static function has(string $feature, ?User $user = null): bool
    {
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
     * Kuota penyimpanan sementara (MB) untuk mahasiswa yang programnya masih
     * menunggu persetujuan dosen (status pending_approval). Setelah disetujui,
     * beban kuota dialihkan ke kuota dosen pembimbing.
     */
    public static function pendingStudentStorageLimitMb(): int
    {
        return self::PENDING_STUDENT_STORAGE_LIMIT_MB;
    }

    /**
     * Metadata kuota untuk keperluan TAMPILAN (mis. kolom "Kuota" di dashboard admin).
     *
     * Berbeda dengan storageLimitMb() yang menghitung "kuota pribadi user", di sini
     * angka yang relevan menyesuaikan role & status program, agar tidak menyesatkan:
     *
     * - Dosen/admin   : pakai storageLimitMb() (pool institusi / plan individual).
     * - Mahasiswa     : tidak punya kuota penyimpanan pribadi yang permanen.
     *     - Jika masih ada program pending_approval/ditolak → 100 MB sementara.
     *     - Jika semua program sudah disetujui (aktif/tamat/nonaktif) → datanya
     *       dibebankan ke kuota dosen pembimbing (storageChargeTarget), sehingga
     *       tidak ada angka kuota mandiri (mb = null).
     *
     * @return array{mb:?int, note:string, legend:?string}
     */
    public static function storageDisplayMetadata(User $user): array
    {
        if (!$user->isMahasiswa()) {
            return [
                'mb' => self::storageLimitMb($user),
                'note' => 'ikut paket/pool',
                'legend' => null,
            ];
        }

        $hasPendingOrRejected = $user->mahasiswaPrograms()
            ->whereIn('status_ta', [
                MahasiswaTa::STATUS_PENDING_APPROVAL,
                MahasiswaTa::STATUS_DITOLAK,
            ])
            ->exists();

        if ($hasPendingOrRejected) {
            return [
                'mb' => self::PENDING_STUDENT_STORAGE_LIMIT_MB,
                'note' => 'sementara (pending approval)',
                'legend' => 'Menunggu persetujuan dosen. Setelah disetujui, beban kuota dialihkan ke dosen pembimbing.',
            ];
        }

        return [
            'mb' => null,
            'note' => 'ikut dosen pembimbing',
            'legend' => 'Program sudah disetujui — data dibebankan ke kuota dosen pembimbing.',
        ];
    }

    /**
     * Batas penyimpanan (MB) untuk user:
     * 1. Override admin — menang mutlak (TIDAK BERUBAH).
     * 2. Base: total langganan direktori (institusi) menang atas plan individual,
     *    TIDAK dijumlah dengan plan individual.
     * 3. Top-up individual — SELALU additive di atas base manapun.
     *
     * Untuk user institusi: batas = min(shared pool institusi, batas per-user).
     * Untuk user personal: batas = plan individual + addon.
     */
    public static function storageLimitMb(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        // 1. Override admin — menang mutlak. Nilai 0 (atau kosong) dianggap
        //    "ikuti paket/pool" (konsisten dengan copy UI), bukan unlimited.
        $override = UserPlanOverride::where('user_id', $user->id)->first();
        if ($override && $override->storage_limit_mb !== null && $override->storage_limit_mb > 0) {
            return (int) $override->storage_limit_mb;
        }

        // 2. User institusi: shared pool institusi (min dengan batas per-user).
        if ($user->institution_id) {
            $poolMb = self::institutionStorageLimitMb($user->institution_id);
            if ($poolMb <= 0) {
                // Institusi tidak punya langganan aktif — fallback ke plan individual.
                return self::individualStorageLimitMb($user);
            }

            // Batas per-user (nullable = unlimited dalam pool).
            $perUserMb = $user->institution_storage_limit_mb;
            if ($perUserMb !== null && $perUserMb > 0) {
                return min($poolMb, (int) $perUserMb) + self::storageAddonMb($user);
            }

            return $poolMb + self::storageAddonMb($user);
        }

        // 3. User personal: plan individual + addon.
        return self::individualStorageLimitMb($user) + self::storageAddonMb($user);
    }

    /**
     * Batas storage individual (plan user, fallback free plan).
     * Bila tak ada sumber kuota jelas: dosen mendapat default 3 GB; selain itu 0.
     */
    private static function individualStorageLimitMb(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        $plan = $user->activePlan();
        $mb = $plan
            ? $plan->storageLimitMb()
            : (Plan::where('name', 'free')->where('is_active', true)->first()?->storageLimitMb() ?? 0);

        if ($mb > 0) {
            return $mb;
        }

        return $user->isDosen() ? self::DEFAULT_STORAGE_LIMIT_MB : 0;
    }

    /**
     * Total kuota dari SEMUA langganan direktori yang berlaku untuk dosen ini,
     * dijumlah lintas cabang berbeda (dedup kalau resolve ke node yang sama).
     * Berlaku untuk user institusi (institution_id terisi).
     */
    public static function directoryStorageLimitMb(?User $user): int
    {
        if (!$user) {
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
                        $total += $sub->poolLimitMb();
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
     * Total pemakaian storage (MB) seluruh user di institusi ini.
     * Dipakai untuk cek shared pool institusi.
     */
    public static function institutionStorageUsedMb(int $institutionId): int
    {
        $userIds = User::where('institution_id', $institutionId)->pluck('id');
        if ($userIds->isEmpty()) {
            return 0;
        }

        $usageService = app(\App\Services\StorageUsageService::class);
        $totalBytes = 0;

        foreach ($userIds as $uid) {
            $user = User::find($uid);
            if ($user) {
                $totalBytes += $usageService->totalBytes($user);
            }
        }

        return (int) floor($totalBytes / 1048576);
    }

    /**
     * Total kuota shared pool institusi (MB) — jumlah semua directory_subscriptions
     * aktif yang ter-cover oleh user-user di institusi ini.
     */
    public static function institutionStorageLimitMb(int $institutionId): int
    {
        // Ambil semua user di institusi ini yang punya afiliasi universitas.
        $userIds = User::where('institution_id', $institutionId)
            ->whereHas('universities')
            ->pluck('id');

        if ($userIds->isEmpty()) {
            return 0;
        }

        // Ambil semua afiliasi user di institusi ini.
        $affiliations = \DB::table('user_university')
            ->whereIn('user_id', $userIds)
            ->get();

        $resolvedSubscriptionIds = [];
        $total = 0;

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
                    if (!in_array($sub->id, $resolvedSubscriptionIds, true)) {
                        $resolvedSubscriptionIds[] = $sub->id;
                        $total += $sub->poolLimitMb();
                    }
                    break;
                }
            }
        }

        return $total;
    }

    /**
     * Cek apakah node direktori tertentu (atau leluhurnya) sudah tercover
     * langganan aktif. Dipakai untuk validasi assign (no-overlap) DAN untuk
     * gate pembuatan akun admin.
     */
    public static function directorySubscriptionActive(string $scopeType, int $scopeId): bool
    {
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
        // Ambil semua user di institusi ini yang punya afiliasi universitas.
        $userIds = User::where('institution_id', $institutionId)
            ->whereHas('universities')
            ->pluck('id');

        if ($userIds->isEmpty()) {
            return false;
        }

        // Ambil semua afiliasi user di institusi ini.
        $affiliations = \DB::table('user_university')
            ->whereIn('user_id', $userIds)
            ->get();

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