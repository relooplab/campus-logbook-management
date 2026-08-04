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

        // 2. Program (TA/KP) yang dibebani ke dosen ini.
        //    Pembimbing 1 (valid) -> fallback pembimbing 2. Penguji tidak dihitung.
        $programIds = MahasiswaTa::where(function ($q) use ($dosen) {
            $q->where('pembimbing_1_id', $dosen->id)
              ->whereHas('pembimbing1', fn ($u) => $u->where('registration_status', 'approved'))
              ->orWhere(function ($q2) use ($dosen) {
                  $q2->where('pembimbing_2_id', $dosen->id)
                     ->where(function ($q3) {
                         $q3->whereNull('pembimbing_1_id')
                            ->orWhereDoesntHave('pembimbing1', fn ($u) => $u->where('registration_status', 'approved'));
                     });
              });
        })->pluck('id');

        // 3. Workspace mahasiswa dari program yang dibebani.
        $total += WorkspaceFile::whereIn('mahasiswa_ta_id', $programIds)->sum('size');

        // 4. Lampiran logbook & revisi dari program yang dibebani.
        $total += LogbookEntry::whereIn('mahasiswa_ta_id', $programIds)
            ->whereNotNull('lampiran_path')
            ->sum('lampiran_size');

        // 5. Foto logbook harian KP dari program yang dibebani.
        $total += $this->logbookHarianKpBytes($programIds);

        return (int) $total;
    }

    private function logbookHarianKpBytes($programIds): int
    {
        if ($programIds->isEmpty()) {
            return 0;
        }

        $total = 0;
        $entries = \App\Models\LogbookHarianKp::whereIn('mahasiswa_ta_id', $programIds)
            ->where(fn ($q) => $q->whereNotNull('foto_1')->orWhereNotNull('foto_2'))
            ->get(['foto_1', 'foto_2']);

        foreach ($entries as $e) {
            foreach (['foto_1', 'foto_2'] as $col) {
                if ($e->{$col}) {
                    try {
                        $total += \Illuminate\Support\Facades\Storage::disk('public')->size($e->{$col});
                    } catch (\Throwable $ex) {
                        // File mungkin tidak ada; abaikan.
                    }
                }
            }
        }

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