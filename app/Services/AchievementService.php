<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\PdfComment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AchievementService
{
    /**
     * Evaluasi & unlock badge untuk mahasiswa pemilik TA.
     * Dipanggil dari listener/event saat status entry berubah.
     */
    public function evaluateForUser(User $user): void
    {
        $ta = $user->mahasiswaTa;
        if (! $ta) {
            return;
        }

        $this->evaluateForProgram($ta);
    }

    /**
     * Evaluasi program tertentu. Dipakai command backfill agar semua institusi
     * dapat dievaluasi tanpa bergantung pada institution scope request aktif.
     */
    public function evaluateForProgram(MahasiswaTa $ta): void
    {
        $user = $ta->mahasiswa;
        if (! $ta->isTa() || ! $user) {
            return;
        }

        $unlocked = collect();

        if ($this->langkahPertama($ta)) {
            $unlocked->push(Achievement::LANGAH_PERTAMA);
        }
        if ($this->konsisten($ta)) {
            $unlocked->push(Achievement::KONSISTEN);
        }
        if ($this->zeroRevisi($ta)) {
            $unlocked->push(Achievement::ZERO_REVISI);
        }
        if ($this->comeback($ta)) {
            $unlocked->push(Achievement::COMEBACK);
        }
        if ($this->setengahJalan($ta)) {
            $unlocked->push(Achievement::SETENGAH_JALAN);
        }
        if ($this->garisAkhir($ta)) {
            $unlocked->push(Achievement::GARIS_AKHIR);
        }
        if ($this->responsif($ta)) {
            $unlocked->push(Achievement::RESPONSIF);
        }
        if ($this->tepatWaktu($ta)) {
            $unlocked->push(Achievement::TEPAT_WAKTU);
        }

        foreach ($unlocked->unique() as $code) {
            $ach = Achievement::where('code', $code)->first();
            if ($ach && ! $user->achievements()->where('achievement_id', $ach->id)->exists()) {
                $user->achievements()->attach($ach->id, ['unlocked_at' => now()]);
            }
        }
    }

    // ------------------------------------------------------------ checks

    private function approvedCount(MahasiswaTa $ta): int
    {
        return $ta->entries()
            ->where('jenis', LogbookEntry::JENIS_LOGBOOK)
            ->where('status', LogbookEntry::STATUS_APPROVED)
            ->count();
    }

    private function langkahPertama(MahasiswaTa $ta): bool
    {
        return $this->approvedCount($ta) >= 1;
    }

    private function konsisten(MahasiswaTa $ta): bool
    {
        // Dua sesi beruntun tanpa jeda > 14 hari. Cari run lokal, jangan
        // menggugurkan progres lama hanya karena ada jeda pada sesi berikutnya.
        $dates = $ta->entries()
            ->where('jenis', LogbookEntry::JENIS_LOGBOOK)
            ->whereNotNull('tanggal_bimbingan')
            ->orderBy('tanggal_bimbingan')
            ->pluck('tanggal_bimbingan')
            ->map(fn ($d) => $d instanceof CarbonInterface ? $d : Carbon::parse($d))
            ->values();

        $run = 0;
        $previous = null;
        foreach ($dates as $date) {
            $run = $previous === null || $date->diffInDays($previous) <= 14 ? $run + 1 : 1;
            if ($run >= 2) {
                return true;
            }
            $previous = $date;
        }

        return false;
    }

    private function zeroRevisi(MahasiswaTa $ta): bool
    {
        // 2 entri approved berturut-turut tanpa revisi di antaranya.
        $seq = $ta->entries()->where('jenis', LogbookEntry::JENIS_LOGBOOK)->orderBy('id')->pluck('status')->values();
        $run = 0;
        foreach ($seq as $s) {
            if (in_array($s, [LogbookEntry::STATUS_REVISI, LogbookEntry::STATUS_ARCHIVED], true)) {
                $run = 0;
            } elseif ($s === LogbookEntry::STATUS_APPROVED) {
                $run++;
                if ($run >= 2) {
                    return true;
                }
            }
        }

        return false;
    }

    private function comeback(MahasiswaTa $ta): bool
    {
        // Revisi adalah child entry. Ukur waktu submit child terhadap waktu
        // feedback/review parent, bukan waktu review submission awal dosen.
        return $ta->entries()
            ->where('jenis', LogbookEntry::JENIS_REVISI)
            ->whereNotNull('parent_entry_id')
            ->whereNotNull('submitted_at')
            ->with('parentEntry:id,reviewed_at')
            ->get()
            ->contains(function (LogbookEntry $revision) {
                $reviewedAt = $revision->parentEntry?->reviewed_at;
                if (! $reviewedAt || $revision->submitted_at->lt($reviewedAt)) {
                    return false;
                }

                return $revision->submitted_at->diffInDays($reviewedAt) < 3;
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
        if ($entryIds->isEmpty()) {
            return false;
        }
        $total = PdfComment::whereIn('logbook_entry_id', $entryIds)->count();
        if ($total === 0) {
            return false;
        }
        $unresolved = PdfComment::whereIn('logbook_entry_id', $entryIds)
            ->whereIn('resolution_status', [PdfComment::STATUS_OPEN, PdfComment::STATUS_ADDRESSED])
            ->count();

        return $unresolved === 0;
    }

    private function tepatWaktu(MahasiswaTa $ta): bool
    {
        // Histori submitted_at tetap berlaku setelah reviewer mengubah status.
        // Dua submit logbook <2 hari setelah bimbingan adalah target awal yang
        // realistis dan tetap mendorong kebiasaan submit tepat waktu.
        $count = $ta->entries()
            ->where('jenis', LogbookEntry::JENIS_LOGBOOK)
            ->whereNotNull('submitted_at')
            ->whereNotNull('tanggal_bimbingan')
            ->get()
            ->filter(function (LogbookEntry $entry) {
                return $entry->submitted_at->diffInDays($entry->tanggal_bimbingan) < 2;
            })
            ->count();

        return $count >= 2;
    }
}
