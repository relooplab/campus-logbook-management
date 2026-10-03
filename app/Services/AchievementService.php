<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\PdfComment;
use App\Models\User;
use App\Notifications\ActivityNotification;
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

        $newlyUnlocked = [];
        foreach ($unlocked->unique() as $code) {
            $ach = Achievement::where('code', $code)->first();
            if ($ach && ! $user->achievements()->where('achievement_id', $ach->id)->exists()) {
                $user->achievements()->attach($ach->id, ['unlocked_at' => now()]);
                $newlyUnlocked[] = $ach;
            }
        }

        if ($newlyUnlocked) {
            $this->notifyUnlocked($user, $newlyUnlocked);
        }
    }

    /**
     * Beri tahu mahasiswa lewat notifikasi in-app + email. Satu evaluasi yang
     * membuka beberapa badge sekaligus (mis. backfill) dirangkum jadi satu
     * notifikasi agar tidak membanjiri inbox.
     */
    private function notifyUnlocked(User $user, array $badges): void
    {
        $badges = collect($badges);
        $message = $badges->count() === 1
            ? '🎉 Achievement baru terkunci: '.$badges->first()->name.' — '.$badges->first()->description
            : '🎉 Kamu membuka '.$badges->count().' achievement baru: '.$badges->map(fn ($badge) => $badge->icon.' '.$badge->name)->join(', ');

        try {
            $user->notify(new ActivityNotification($message, route('dashboard'), 'Achievement Baru Terkunci'));
        } catch (\Throwable $e) {
            report($e);
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
            // diffInDays(a) = (a - this): dari tanggal sebelumnya ke berikutnya
            // hasilnya positif saat jeda normal; reset bila jeda > 14 hari.
            $gap = $previous !== null ? $previous->diffInDays($date) : 0;
            $run = ($gap >= 0 && $gap <= 14) ? $run + 1 : 1;
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

                // diffInDays(a) = (a - this): balik argumen agar hasil positif
                // saat child dikirim setelah review, lalu batasi benar-benar < 3 hari.
                $delay = $reviewedAt->diffInDays($revision->submitted_at);
                return $delay > 0 && $delay < 3;
            });
    }

    private function setengahJalan(MahasiswaTa $ta): bool
    {
        // Mengacu pada fase TA, bukan jumlah sesi: unlock ketika fase sudah
        // mencapai Seminar Hasil (termasuk fase sesudahnya: Draft Sidang,
        // Sidang, Achievement Unlocked). Program KP tidak memiliki fase ini.
        if ($ta->jenis !== MahasiswaTa::JENIS_TA) {
            return false;
        }

        $phaseOrder = array_keys(MahasiswaTa::FASES);
        $current = array_search($ta->fase, $phaseOrder, true);
        $target = array_search('seminar_hasil', $phaseOrder, true);

        return $current !== false && $current >= $target;
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
                // diffInDays(a) = (a - this): balik argumen agar gap positif
                // (submit setelah bimbingan) dan benar-benar < 2 hari.
                $gap = Carbon::parse($entry->tanggal_bimbingan)->diffInDays($entry->submitted_at);
                return $gap >= 0 && $gap < 2;
            })
            ->count();

        return $count >= 2;
    }
}
