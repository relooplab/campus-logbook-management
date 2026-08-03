<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catat waktu terakhir aktif user login.
 * Menggunakan throttling: hanya menulis ke DB jika > 1 menit sejak update terakhir,
 * sehingga tidak membebani database pada setiap request.
 */
class UpdateLastActive
{
    /**
     * Interval minimum (detik) sebelum menulis ulang last_active_at.
     */
    protected const THROTTLE_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user) {
            $last = $user->last_active_at;

            if (! $last || $last->diffInSeconds(now()) >= self::THROTTLE_SECONDS) {
                $user->update(['last_active_at' => now()]);
            }
        }

        return $next($request);
    }
}