<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * Kegagalan saat membuat backup (disk penuh, mysqldump gagal, dll) —
 * pesannya aman ditampilkan langsung ke system_admin.
 */
class BackupException extends RuntimeException
{
}
