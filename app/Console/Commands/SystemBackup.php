<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupException;
use App\Services\SystemBackupService;
use Illuminate\Console\Command;

/**
 * Wrapper CLI tipis untuk SystemBackupService — berguna untuk recovery via
 * SSH kalau system_admin terkunci dari web UI. Logic sesungguhnya ada di
 * service, bukan di sini.
 */
class SystemBackup extends Command
{
    protected $signature = 'system:backup
                            {--output= : Path tujuan file ZIP (default: simpan di storage/framework/backup-tmp)}
                            {--module=* : Modul yang mau di-backup (kosong = full backup seluruh sistem)}';

    protected $description = 'Backup seluruh sistem (database + storage) atau sebagian modul saja, jadi satu file ZIP';

    public function handle(SystemBackupService $service): int
    {
        $modules = $this->option('module') ?: null;

        try {
            $zipPath = $service->create($modules);
        } catch (BackupException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $output = $this->option('output');
        if ($output) {
            rename($zipPath, $output);
            $zipPath = $output;
        }

        $this->info("Backup berhasil dibuat: {$zipPath}");

        return self::SUCCESS;
    }
}
