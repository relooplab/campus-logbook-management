<?php

namespace App\Console\Commands;

use App\Models\Institution;
use App\Models\LogbookEntry;
use App\Models\LogbookHarianKp;
use App\Models\SeminarSubmission;
use App\Models\ThesisFinalization;
use App\Models\User;
use App\Models\WorkspaceFile;
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
        $cutoff = now()->subDays($days);

        // Kumpulkan semua path yang masih direferensikan (disk local).
        $localReferenced = collect()
            // Logbook lampiran & catatan.
            ->merge(LogbookEntry::query()->pluck('lampiran_path'))
            ->merge(LogbookEntry::query()->pluck('catatan_perbaikan_path'))
            // Workspace (mahasiswa + dosen).
            ->merge(WorkspaceFile::query()->pluck('path'))
            // Seminar-materials (undangan & materi).
            ->merge(SeminarSubmission::query()->pluck('undangan_path'))
            ->merge(SeminarSubmission::query()->pluck('materi_path'))
            // Finalization.
            ->merge(ThesisFinalization::query()->pluck('cover_path'))
            ->merge(ThesisFinalization::query()->pluck('pengesahan_path'))
            ->merge(ThesisFinalization::query()->pluck('full_file_path'))
            // Logbook harian KP (foto).
            ->merge(LogbookHarianKp::query()->pluck('foto_1'))
            ->merge(LogbookHarianKp::query()->pluck('foto_2'))
            // Logo institusi.
            ->merge(Institution::query()->pluck('logo_path'))
            ->filter()
            ->all();

        // Path yang direferensikan di disk public (foto profil).
        $publicReferenced = collect()
            ->merge(User::query()->pluck('profile_photo_path'))
            ->filter()
            ->all();

        $pruned = 0;

        // Scan disk local.
        $localDisk = Storage::disk('local');
        foreach (['lampiran', 'catatan', 'workspace', 'seminar-materials', 'finalization', 'logbook-harian', 'institution'] as $dir) {
            if (!$localDisk->exists($dir)) {
                continue;
            }
            $files = $localDisk->allFiles($dir);
            foreach ($files as $path) {
                if (in_array($path, $localReferenced, true)) {
                    continue;
                }
                if ($localDisk->lastModified($path) < $cutoff->timestamp) {
                    $localDisk->delete($path);
                    Log::channel('audit')->info("Orphan file pruned: {$path}");
                    $pruned++;
                }
            }
        }

        // Scan disk public (foto profil).
        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists('profiles')) {
            $files = $publicDisk->allFiles('profiles');
            foreach ($files as $path) {
                if (in_array($path, $publicReferenced, true)) {
                    continue;
                }
                if ($publicDisk->lastModified($path) < $cutoff->timestamp) {
                    $publicDisk->delete($path);
                    Log::channel('audit')->info("Orphan file pruned: {$path}");
                    $pruned++;
                }
            }
        }

        $this->info("{$pruned} file orphan dibersihkan.");

        return self::SUCCESS;
    }
}