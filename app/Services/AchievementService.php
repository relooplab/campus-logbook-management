<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\PdfComment;
use App\Models\User;

class AchievementService
{
    /**
     * Evaluasi & unlock badge untuk mahasiswa pemilik TA.
     * Dipanggil dari listener/event saat status entry berubah.
     */
    public function evaluateForUser(User $user): void
    {
        $ta = $user->mahasiswaTa;
        if (!$ta) {
            return;
        }

        $unlocked = collect();

        if ($this->langkahPertama($ta)) $unlocked->push(Achievement::LANGAH_PERTAMA);
        if ($this->konsisten($ta)) $unlocked->push(Achievement::KONSISTEN);
        if ($this->zeroRevisi($ta)) $unlocked->push(Achievement::ZERO_REVISI);
        if ($this->comeback($ta)) $unlocked->push(Achievement::COMEBACK);
        if ($this->setengahJalan($ta)) $unlocked->push(Achievement::SETENGAH_JALAN);
        if ($this->garisAkhir($ta)) $unlocked->push(Achievement::GARIS_AKHIR);
        if ($this->responsif($ta)) $unlocked->push(Achievement::RESPONSIF);
        if ($this->tepatWaktu($ta)) $unlocked->push(Achievement::TEPAT_WAKTU);

        foreach ($unlocked->unique() as $code) {
            $ach = Achievement::where('code', $code)->first();
            if ($ach && !$user->achievements()->where('achievement_id', $ach->id)->exists()) {
                $user->achievements()->attach($ach->id, ['unlocked_at' => now()]);
            }
        }
    }

    // ------------------------------------------------------------ checks

    private function approvedCount(MahasiswaTa $ta): int
    {
        return $ta->entries()->where('status', LogbookEntry::STATUS_APPROVED)->count();
    }

    private function langkahPertama(MahasiswaTa $ta): bool
    {
        return $this->approvedCount($ta) >= 1;
    }

    private function konsisten(MahasiswaTa $ta): bool
    {
        // 4 sesi logbook beruntun tanpa jeda > 14 hari antar tanggal bimbingan.
        $dates = $ta->entries()
            ->where('jenis', LogbookEntry::JENIS_LOGBOOK)
            ->whereNotNull('tanggal_bimbingan')
            ->orderBy('tanggal_bimbingan')
            ->pluck('tanggal_bimbingan')
            ->map(fn ($d) => $d instanceof \Carbon\CarbonInterface ? $d : \Carbon\Carbon::parse($d))
            ->values();

        if ($dates->count() < 4) return false;

        for ($i = 1; $i < $dates->count(); $i++) {
            $gap = $dates[$i]->diffInDays($dates[$i - 1]);
            if ($gap > 14) return false;
        }
        return true;
    }

    private function zeroRevisi(MahasiswaTa $ta): bool
    {
        // 3 entri approved berturut-turut tanpa revisi di antaranya.
        $seq = $ta->entries()->orderBy('id')->pluck('status')->values();
        $run = 0;
        foreach ($seq as $s) {
            if ($s === LogbookEntry::STATUS_REVISI) {
                $run = 0;
            } elseif ($s === LogbookEntry::STATUS_APPROVED) {
                $run++;
                if ($run >= 3) return true;
            }
        }
        return false;
    }

    private function comeback(MahasiswaTa $ta): bool
    {
        // Entry yang pernah diminta revisi (ada feedback_dosen & reviewed_at)
        // lalu dikirim ulang (submitted_at) dalam < 3 hari.
        return $ta->entries()
            ->whereNotNull('feedback_dosen')
            ->whereNotNull('reviewed_at')
            ->whereNotNull('submitted_at')
            ->get()
            ->contains(function ($e) {
                $delay = \Carbon\Carbon::parse($e->submitted_at)->diffInDays($e->reviewed_at);
                return $delay < 3;
            });
    }

    private function setengahJalan(MahasiswaTa $ta): bool
    {
        $target = $ta->target_sesi ?? 7;
        return $target > 0 && $this->approvedCount($ta) >= $target / 2;
    }

    private function garisAkhir(MahasiswaTa $ta): bool
    {
        $target = $ta->target_sesi ?? 7;
        return $target > 0 && $this->approvedCount($ta) >= $target;
    }

    private function responsif(MahasiswaTa $ta): bool
    {
        // Semua komentar PDF di semua entri sudah resolve.
        $entryIds = $ta->entries()->pluck('id');
        if ($entryIds->isEmpty()) return false;
        $total = PdfComment::whereIn('logbook_entry_id', $entryIds)->count();
        if ($total === 0) return false;
        $unresolved = PdfComment::whereIn('logbook_entry_id', $entryIds)->where('is_resolved', false)->count();
        return $unresolved === 0;
    }

    private function tepatWaktu(MahasiswaTa $ta): bool
    {
        // Submit < 2 hari setelah tanggal_bimbingan, minimal 5x.
        $count = $ta->entries()
            ->where('status', LogbookEntry::STATUS_SUBMITTED)
            ->whereNotNull('submitted_at')
            ->whereNotNull('tanggal_bimbingan')
            ->get()
            ->filter(function ($e) {
                $gap = \Carbon\Carbon::parse($e->submitted_at)->diffInDays($e->tanggal_bimbingan);
                return $gap < 2;
            })
            ->count();

        return $count >= 5;
    }
}
