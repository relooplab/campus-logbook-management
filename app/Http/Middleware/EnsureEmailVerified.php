<?php

namespace App\Http\Middleware;

use App\Models\Institution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate verifikasi email: hanya aktif ketika system admin mengaktifkan
 * setting "Wajib Verifikasi Email" di panel Pengaturan. Saat aktif,
 * user yang login tapi email belum diverifikasi tidak dapat mengakses
 * fitur aplikasi; akan diarahkan ke halaman 'verification.notice'.
 */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip untuk halaman verifikasi itu sendiri (notice/verify/send/logout)
        // agar user yang belum verified tetap bisa mengirim ulang tautan atau keluar.
        $routeName = $request->route()?->getName();
        if (in_array($routeName, ['verification.notice', 'verification.verify', 'verification.send', 'logout'], true)) {
            return $next($request);
        }

        // Nonaktif di env testing agar fixture test tidak terblokir.
        if (! config('app.enforce_email_verification')) {
            return $next($request);
        }

        // Query langsung (skip cache) untuk konsistensi dengan controller auth.
        $required = Institution::emailVerificationRequiredNow();

        $user = $request->user();

        if ($user && $required && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice')
                ->with('warning', 'Silakan verifikasi email Anda terlebih dahulu.');
        }

        return $next($request);
    }
}
