<?php

namespace App\Console\Commands;

use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hapus mahasiswa yang sudah memilih dosen (program pending_approval) tetapi
 * tidak disetujui dosen dalam 1 bulan sejak request dibuat.
 *
 * Basis hitung = created_at dari MahasiswaTa (saat mahasiswa memilih dosen),
 * sehingga mahasiswa punya jendela penuh 1 bulan untuk diproses dosen.
 * Hanya akun yang TIDAK punya program lain yang sudah aktif/tamat/nonaktif
 * yang dihapus (jangan sampai menghapus data mahasiswa yang sudah aktif).
 */
class DeleteInactiveStudents extends Command
{
    protected $signature = 'students:delete-inactive';

    protected $description = 'Hapus mahasiswa ber-program pending yang tidak disetujui dosen dalam 1 bulan';

    public function handle(): int
    {
        $cutoff = now()->subMonth();

        // Program yang masih menunggu persetujuan dosen dan sudah berumur > 1 bulan.
        $pendingOld = MahasiswaTa::where('status_ta', MahasiswaTa::STATUS_PENDING_APPROVAL)
            ->where('created_at', '<', $cutoff)
            ->with('mahasiswa')
            ->get();

        // Dedup per mahasiswa (seorang mahasiswa bisa punya lebih dari satu request pending).
        $targets = $pendingOld
            ->filter(fn ($ta) => $ta->mahasiswa !== null)
            ->unique('user_id');

        if ($targets->isEmpty()) {
            $this->info('Tidak ada mahasiswa ber-program pending > 1 bulan yang perlu dihapus.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($targets as $ta) {
            $user = $ta->mahasiswa;

            // Aman: jangan hapus akun yang sudah punya program disetujui (aktif/tamat/nonaktif),
            // mis. mahasiswa yang sudah lanjut ke program lain.
            $hasApprovedProgram = $user->mahasiswaPrograms()
                ->whereNotIn('status_ta', [MahasiswaTa::STATUS_PENDING_APPROVAL, MahasiswaTa::STATUS_DITOLAK])
                ->exists();

            if ($hasApprovedProgram) {
                continue;
            }

            // Hapus program pending + user (cascade ke data terkait) secara atomik.
            DB::transaction(function () use ($user) {
                $user->mahasiswaPrograms()->delete();
                $user->delete();
            });

            $count++;
            $this->line("Dihapus: {$user->name} ({$user->email})");
        }

        $this->info("{$count} mahasiswa ber-program pending (tidak disetujui > 1 bulan) dihapus.");
        return self::SUCCESS;
    }
}