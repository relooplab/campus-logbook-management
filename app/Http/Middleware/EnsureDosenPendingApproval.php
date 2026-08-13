<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate keputusan dosen (gate LUNAK berbasis batas waktu).
 *
 * Bila ada mahasiswa ber-status `pending_approval` yang menjadi tanggung jawab
 * dosen ini (pembimbing/penguji yang dipilih) dan SUDAH MELEWATI batas waktu
 * (default 4 hari), dosen diarahkan ke halaman Persetujuan dan di-block dari
 * fitur lain sampai memilih Approve / Tolak. Sebelum lewat batas, dosen bebas
 * (hanya diingatkan via papan peringatan di layout).
 *
 * Route yang tetap terbuka: keputusan persetujuan/penguji, logout, verifikasi
 * email/ubah email/password/afiliasi, dan review bahan mahasiswa pending
 * (logbook* / mahasiswa-ta* / logbook-harian*) agar bisa menilai sebelum memutuskan.
 *
 * Nonaktif di env testing (pola sama seperti gate lain).
 */
class EnsureDosenPendingApproval
{
    private const ALLOWED = [
        'logout',
        'verification.notice', 'verification.send',
        'profile.email', 'profile.password',
        'profile.affiliation', 'profile.affiliation.update', 'profile.affiliation.revoke',
        'approval.index', 'approval.invite', 'approval.approve', 'approval.reject',
        'approval.penguji.approve', 'approval.penguji.reject',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.enforce_dosen_pending_approval')) {
            return $next($request);
        }

        $user = $request->user();
        $days = (int) config('app.dosen_pending_approval_deadline_days', 4);

        if ($user && $user->isDosen() && $user->hasPendingApprovalOverdue($days)) {
            $routeName = (string) $request->route()?->getName();

            $isReviewRoute = str_starts_with($routeName, 'logbook.')
                || str_starts_with($routeName, 'mahasiswa-ta.')
                || str_starts_with($routeName, 'logbook-harian.');

            if (in_array($routeName, self::ALLOWED, true) || $isReviewRoute) {
                return $next($request);
            }

            return redirect()->route('approval.index')
                ->with('warning', 'Selesaikan persetujuan mahasiswa yang sudah melewati batas waktu terlebih dahulu.');
        }

        return $next($request);
    }
}