<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanOverride;

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
            'koordinator',
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
     * Batas penyimpanan (MB) untuk user: override admin > plan aktif > plan free (default).
     */
    public static function storageLimitMb(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        $override = UserPlanOverride::where('user_id', $user->id)->first();
        if ($override && $override->storage_limit_mb !== null) {
            return (int) $override->storage_limit_mb;
        }

        $plan = $user->activePlan();
        if ($plan) {
            return $plan->storageLimitMb();
        }

        // Default: paket free (5 GB) jika user belum punya plan/subscription.
        $freePlan = Plan::where('name', 'free')->where('is_active', true)->first();
        if ($freePlan) {
            return $freePlan->storageLimitMb();
        }

        return 0;
    }
}
