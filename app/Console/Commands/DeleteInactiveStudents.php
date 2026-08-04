<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hapus mahasiswa yang statusnya "active" (sudah verifikasi email, belum
 * attach dosen) tetapi tidak menjadi "verified" dalam 1 bulan.
 * Menghapus bersih: akun user + semua data terkait (MahasiswaTa, logbook, dll).
 */
class DeleteInactiveStudents extends Command
{
    protected $signature = 'students:delete-inactive';

    protected $description = 'Hapus mahasiswa active yang tidak verified dalam 1 bulan';

    public function handle(): int
    {
        $cutoff = now()->subMonth();

        $inactive = User::role('mahasiswa')
            ->where('registration_status', 'active')
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($inactive->isEmpty()) {
            $this->info('Tidak ada mahasiswa active yang perlu dihapus.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($inactive as $user) {
            // Hapus dalam transaksi agar konsisten.
            DB::transaction(function () use ($user) {
                // Hapus MahasiswaTa (cascade ke logbook, workspace, dll).
                $user->mahasiswaPrograms()->delete();
                // Hapus user (cascade ke relasi lain via FK).
                $user->delete();
            });
            $count++;
            $this->line("Dihapus: {$user->name} ({$user->email})");
        }

        $this->info("{$count} mahasiswa active dihapus (tidak verified dalam 1 bulan).");
        return self::SUCCESS;
    }
}