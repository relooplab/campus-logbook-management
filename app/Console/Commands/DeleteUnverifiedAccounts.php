<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hapus akun yang TIDAK PERNAH verifikasi email dalam N hari.
 *
 * Karena NIM/NIDN kini diisi setelah verifikasi email, akun unverified biasanya
 * tidak membawa identitas — pembersihan ini membebaskan email (dan identitas apa
 * pun yang terlanjur terpasang) milik akun yang terabandon, sekaligus mencegah
 * "penyerobotan identitas" yang hanya mendaftar lalu tidak dikonfirmasi.
 * Akun admin/system_admin tidak pernah dihapus otomatis.
 */
class DeleteUnverifiedAccounts extends Command
{
    protected $signature = 'users:delete-unverified {--days=7 : Hapus akun belum verifikasi yang lebih lama dari N hari}';

    protected $description = 'Hapus akun yang tidak pernah verifikasi email dalam N hari.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $targets = User::with('roles')
            ->whereNull('email_verified_at')
            ->where('created_at', '<', $cutoff)
            ->get()
            // Jangan pernah menghapus akun admin/system_admin otomatis.
            ->filter(fn (User $u) => ! $u->hasAnyRole(['admin', 'system_admin']));

        if ($targets->isEmpty()) {
            $this->info("Tidak ada akun belum-verifikasi lebih dari {$days} hari yang perlu dihapus.");

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($targets as $user) {
            $email = $user->email;
            $name = $user->name;

            DB::transaction(function () use ($user) {
                $user->delete();
            });

            $this->line("Dihapus: {$name} ({$email})");
            $count++;
        }

        $this->info("$count akun belum-verifikasi (> {$days} hari) dihapus.");

        return self::SUCCESS;
    }
}
