<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Audit log untuk aksi sensitif (admin & auth) agar bisa di-forensik.
 * Menulis ke channel 'audit' (daily — storage/logs/audit-YYYY-MM-DD.log).
 */
class Audit
{
    public static function log(string $action, array $context = []): void
    {
        Log::channel('audit')->info($action, array_merge([
            'oleh' => auth()->user()
                ? auth()->user()->name.' ('.auth()->id().')'
                : 'anonymous',
            'ip' => request()->ip(),
            'waktu' => now()->toDateTimeString(),
        ], $context));
    }
}
