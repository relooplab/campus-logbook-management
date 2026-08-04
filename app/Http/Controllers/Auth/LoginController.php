<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = $request->user();

            // Dosen yang belum disetujui admin (pending) belum boleh login.
            if ($user->isDosen() && $user->registration_status === 'pending') {
                Auth::logout();
                $request->session()->invalidate();

                return back()
                    ->withErrors(['email' => 'Akun dosen masih menunggu persetujuan admin.'])
                    ->onlyInput('email');
            }

            // Mahasiswa yang ditolak tidak boleh login.
            if ($user->isMahasiswa() && $user->registration_status === 'rejected') {
                Auth::logout();
                $request->session()->invalidate();

                return back()
                    ->withErrors(['email' => 'Akun Anda ditolak. Hubungi admin.'])
                    ->onlyInput('email');
            }

            $request->session()->regenerate();

            // Jika email belum diverifikasi, arahkan ke halaman verifikasi.
            if (!$user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            return redirect()->intended(route('dashboard'));
        }

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
