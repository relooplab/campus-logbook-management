<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * Kegagalan yang terjadi SETELAH langkah destruktif restore sudah dimulai
 * (mis. import SQL gagal di tengah jalan) — berbeda dari
 * RestoreValidationException yang selalu dilempar SEBELUM apapun disentuh.
 * Pesan harus menyebut bahwa safety-backup otomatis tersedia untuk recovery.
 */
class RestoreException extends RuntimeException
{
}
