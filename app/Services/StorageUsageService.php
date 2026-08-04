<?php

namespace App\Services;

use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\User;
use App\Models\WorkspaceFile;

/**
 * Menghitung pemakaian penyimpanan (bytes) untuk seorang user.
 *
 * Untuk dosen: total dari workspace pribadi dosen + seluruh file & lampiran
 * dari mahasiswa yang dibimbingnya (logbook, revisi, workspace mahasiswa).
 * Untuk mahasiswa: total dari workspace & lampiran miliknya.
 */
class StorageUsageService
{
    /**
     * Total pemakaian (bytes) untuk user.
     */
    public function totalBytes(User $user): int
    {
        if ($user->isDosen()) {
            return $this->dosenBytes($user);
        }

        return $this->mahasiswaBytes($user);
    }

    /**
     * Pemakaian dosen = workspace pribadi + data mahasiswa bimbingan.
     */
    private function dosenBytes(User $dosen): int
    {
        $total = 0;

        // 1. Workspace pribadi dosen.
        $total += WorkspaceFile::where('user_id', $dosen->id)->sum('size');

        // 2. Ids mahasiswa yang dibimbing dosen.
        $taIds = MahasiswaTa::where('pembimbing_1_id', $dosen->id)
            ->orWhere('pembimbing_2_id', $dosen->id)
            ->pluck('id');

        // 3. Workspace mahasiswa bimbingan.
        $total += WorkspaceFile::whereIn('mahasiswa_ta_id', $taIds)->sum('size');

        // 4. Lampiran logbook & revisi mahasiswa bimbingan.
        $total += LogbookEntry::whereIn('mahasiswa_ta_id', $taIds)
            ->whereNotNull('lampiran_path')
            ->sum('lampiran_size');

        return (int) $total;
    }

    /**
     * Pemakaian mahasiswa = workspace + lampiran miliknya.
     */
    private function mahasiswaBytes(User $mahasiswa): int
    {
        $total = 0;

        $taIds = $mahasiswa->mahasiswaPrograms()->pluck('id');

        $total += WorkspaceFile::whereIn('mahasiswa_ta_id', $taIds)->sum('size');

        $total += LogbookEntry::whereIn('mahasiswa_ta_id', $taIds)
            ->whereNotNull('lampiran_path')
            ->sum('lampiran_size');

        return (int) $total;
    }
}