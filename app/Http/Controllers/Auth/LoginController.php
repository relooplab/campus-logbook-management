<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            // Boleh berupa email, NIM (mahasiswa), atau NIDN (dosen).
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Jika bukan email (berisi '@'), anggap NIM/NIDN → resolve ke email pemiliknya.
        $login = trim($credentials['email']);
        $attemptEmail = $login;
        if (! str_contains($login, '@')) {
            $owner = \App\Models\User::findByIdentifier($login);
            if ($owner) {
                $attemptEmail = $owner->email;
            }
        }

        if (Auth::attempt(['email' => $attemptEmail, 'password' => $credentials['password']], $request->boolean('remember'))) {
            $user = $request->user();

            // Akun yang benar-benar ditolak/dinonaktifkan tidak boleh login.
            if ($user->registration_status === 'rejected') {
                \App\Support\Audit::log('Login ditolak (akun rejected)', ['email' => $credentials['email'], 'user_id' => $user->id]);
                Auth::logout();
                $request->session()->invalidate();

                return back()
                    ->withErrors(['email' => 'Akun Anda ditolak. Hubungi admin.'])
                    ->onlyInput('email');
            }

            $request->session()->regenerate();

            \App\Support\Audit::log('Login berhasil', ['email' => $credentials['email'], 'user_id' => $user->id]);

            // Query langsung (skip cache) — lihat komentar di RegisterController.
            $verificationRequired = (bool) Institution::query()->value('email_verification_required');

            // Jika system admin mengaktifkan verifikasi email wajib dan user
            // belum verifikasi, arahkan ke halaman notice.
            if ($verificationRequired && ! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            return redirect()->intended(route('dashboard'));
        }

        \App\Support\Audit::log('Login gagal', ['email' => $credentials['email']]);

        return back()
            ->withErrors(['email' => 'Kredensial tidak cocok.'])
            ->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}