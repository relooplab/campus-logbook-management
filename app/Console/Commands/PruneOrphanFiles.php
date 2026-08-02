<?php

namespace App\Console\Commands;

use App\Models\LogbookEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PruneOrphanFiles extends Command
{
    protected $signature = 'files:prune-orphans
                            {--days=30 : Umur minimum file orphan (hari)}';

    protected $description = 'Bersihkan file lampiran yang tidak lagi direferensikan (orphan) setelah buffer N hari';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        // Kumpulkan semua path yang masih direferensikan.
        $referenced = LogbookEntry::query()
            ->pluck('lampiran_path')
            ->merge(LogbookEntry::query()->pluck('catatan_perbaikan_path'))
            ->filter()
            ->all();

        $cutoff = now()->subDays($days);
        $disk = Storage::disk('local');

        // Scan direktori lampiran & catatan (path unik {dir}/{entry_id}/{uuid}).
        $pruned = 0;
        foreach (['lampiran', 'catatan'] as $dir) {
            if (!$disk->exists($dir)) {
                continue;
            }
            $files = $disk->allFiles($dir);
            foreach ($files as $path) {
                if (in_array($path, $referenced, true)) {
                    continue;
                }
                if ($disk->lastModified($path) < $cutoff->timestamp) {
                    $disk->delete($path);
                    Log::channel('audit')->info("Orphan file pruned: {$path}");
                    $pruned++;
                }
            }
        }

        $this->info("{$pruned} file orphan dibersihkan.");

        return self::SUCCESS;
    }
}
