<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Endpoint verifikasi email (notice / verify / resend).
 * Dipakai ketika system admin mengaktifkan toggle "Wajib Verifikasi Email".
 */
class VerificationController extends Controller
{
    /**
     * Tampilkan halaman "cek email Anda" + tombol kirim ulang.
     */
    public function showNotice(): View
    {
        return view('auth.verify-email');
    }

    /**
     * Verifikasi email via signed URL (dari email).
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Email Anda berhasil diverifikasi.');
    }

    /**
     * Kirim ulang email verifikasi (throttled). Route name: verification.send.
     */
    public function resend(Request $request): RedirectResponse
    {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
