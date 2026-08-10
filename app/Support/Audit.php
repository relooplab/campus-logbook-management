<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * Audit log untuk aksi sensitif (admin & auth) agar bisa di-forensik.
 * Menulis ke channel 'audit' (daily — storage/logs/audit-YYYY-MM-DD.log).
 */
class Audit
{
    public static function log(string $action, array $context = []): void
    {
        $user = Auth::user();
        Log::channel('audit')->info($action, array_merge([
            'oleh' => $user
                ? $user->name.' ('.Auth::id().')'
                : 'system',
            'ip' => app()->bound('request') && ($req = request()) instanceof Request
                ? $req->ip()
                : null,
            'waktu' => now()->toDateTimeString(),
        ], $context));
    }
}
