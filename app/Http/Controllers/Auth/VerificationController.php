<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
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
     * Verifikasi email via signed link (dari email).
     * Bisa dipakai tanpa login: keabsahan URL ditangani middleware `signed`
     * dan `hash` dibandingkan dengan email user. Setelah sukses, user di-login.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        if (! $user || ! hash_equals((string) $hash, sha1($user->email))) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            Auth::login($user);

            return redirect()->intended(route('dashboard'));
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        Auth::login($user);

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
