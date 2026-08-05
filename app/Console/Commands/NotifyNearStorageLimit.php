<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\StorageQuotaWarningNotification;
use App\Services\StorageUsageService;
use App\Support\Feature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyNearStorageLimit extends Command
{
    protected $signature = 'storage:notify-near-limit';
    protected $description = 'Kirim notifikasi proaktif ke dosen ketika kuota penyimpanan mendekati limit (80% & 95%)';

    public function handle(StorageUsageService $usageService): int
    {
        $thresholds = [80, 95];
        $sent = 0;

        // Semua dosen.
        $dosens = User::role('dosen')->get();

        foreach ($dosens as $dosen) {
            $limitMb = Feature::storageLimitMb($dosen);
            if ($limitMb <= 0) {
                continue; // unlimited
            }

            $usedBytes = $usageService->totalBytes($dosen);
            $usedMb = (int) floor($usedBytes / 1048576);
            $percent = (int) floor($usedMb / $limitMb * 100);

            // Cari threshold tertinggi yang dilewati.
            $crossed = null;
            foreach ($thresholds as $t) {
                if ($percent >= $t) {
                    $crossed = $t;
                }
            }

            if (!$crossed) {
                continue;
            }

            // Anti-spam: kirim hanya jika belum pernah di threshold ini.
            $alreadyNotified = DB::table('storage_quota_notifications')
                ->where('user_id', $dosen->id)
                ->where('threshold', $crossed)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $dosen->notify(new StorageQuotaWarningNotification($crossed, $usedMb, $limitMb));

            DB::table('storage_quota_notifications')->updateOrInsert(
                ['user_id' => $dosen->id, 'threshold' => $crossed],
                ['notified_at' => now(), 'updated_at' => now()]
            );

            $sent++;
            $this->line("Notifikasi {$crossed}% ke {$dosen->email} ({$usedMb} MB / {$limitMb} MB)");
        }

        $this->info("Selesai. {$sent} notifikasi terkirim.");

        return self::SUCCESS;
    }
}