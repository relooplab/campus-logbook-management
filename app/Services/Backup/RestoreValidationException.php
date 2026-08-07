<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * ZIP restore tidak valid (struktur tidak cocok, manifest rusak, atau backup
 * parsial di-upload padahal belum didukung) — pesannya aman ditampilkan
 * langsung ke system_admin. Dilempar SEBELUM langkah destruktif apapun.
 */
class RestoreValidationException extends RuntimeException
{
}
