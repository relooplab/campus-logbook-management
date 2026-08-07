<?php

namespace App\Console\Commands;

use App\Models\SidangGrade;
use App\Notifications\ActivityNotification;
use Illuminate\Console\Command;

/**
 * Ingatkan dosen (pembimbing & penguji) mengisi nilai seminar/sidang
 * yang tanggalnya sudah lewat dan nilainya belum lengkap.
 */
class RemindSidangGrading extends Command
{
    protected $signature = 'sidang:remind-grading';

    protected $description = 'Ingatkan dosen mengisi nilai seminar/sidang yang sudah berlangsung.';

    public function handle(): int
    {
        $now = now();
        $grades = SidangGrade::whereNull('filled_at')
            ->whereHas('sidang', fn ($q) => $q->where('tanggal', '<=', $now->toDateString()))
            ->with(['user', 'sidang.mahasiswaTa.mahasiswa'])
            ->get();

        $notified = 0;
        foreach ($grades as $g) {
            $user = $g->user;
            if (! $user) {
                continue;
            }

            $sidang = $g->sidang;
            $mhs = $sidang->mahasiswa_name ?? $sidang->mahasiswaTa?->mahasiswa?->name ?? 'Mahasiswa';

            // Notifikasi ulang setiap 7 hari sampai diisi.
            if ($g->reminded_at && $g->reminded_at->diffInDays($now) < 7) {
                continue;
            }

            $user->notify(new ActivityNotification(
                "Anda belum mengisi nilai untuk {$sidang->jenisLabel()} {$mhs} ({$sidang->tanggal?->format('d M Y')}).",
                route('dosen-sidang.index'),
                'Pengingat Isi Nilai Seminar/Sidang'
            ));

            $g->update(['reminded_at' => $now]);
            $notified++;
        }

        $this->info("Pengingat nilai dikirim ke {$notified} penilai.");

        return self::SUCCESS;
    }
}
