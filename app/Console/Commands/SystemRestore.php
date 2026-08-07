<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupException;
use App\Services\Backup\RestoreException;
use App\Services\Backup\RestoreValidationException;
use App\Services\SystemRestoreService;
use Illuminate\Console\Command;

/**
 * Wrapper CLI tipis untuk SystemRestoreService — berguna untuk recovery via
 * SSH kalau system_admin terkunci dari web UI. Logic sesungguhnya ada di
 * service, bukan di sini.
 */
class SystemRestore extends Command
{
    protected $signature = 'system:restore
                            {path : Path ke file ZIP backup}
                            {--force : Lewati prompt konfirmasi interaktif}';

    protected $description = 'Restore seluruh sistem (database + storage) dari file ZIP backup — DESTRUKTIF & IREVERSIBEL';

    public function handle(SystemRestoreService $service): int
    {
        $path = $this->argument('path');

        if (!is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        if (!$this->option('force')) {
            $this->warn('PERINGATAN: Restore akan MENGHAPUS SEMUA DATA saat ini dan menggantinya dengan isi backup.');
            $this->warn('Aksi ini destruktif & tidak bisa dibatalkan (safety-backup otomatis akan dibuat dulu).');
            if (!$this->confirm('Lanjutkan restore?')) {
                $this->info('Dibatalkan.');

                return self::SUCCESS;
            }
        }

        try {
            $result = $service->restore($path);
        } catch (RestoreValidationException $e) {
            $this->error('Backup tidak valid: '.$e->getMessage());

            return self::FAILURE;
        } catch (BackupException|RestoreException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Restore berhasil.');
        $this->info('Safety-backup (kondisi sebelum restore) disimpan di: '.$result['safety_backup_path']);

        return self::SUCCESS;
    }
}
