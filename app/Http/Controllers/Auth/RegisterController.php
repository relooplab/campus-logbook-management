<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Registrasi mandiri mahasiswa & dosen.
 * - Mahasiswa: akun dibuat role 'mahasiswa' status ACTIVE (langsung aktif, tanpa verifikasi email).
 * - Dosen: akun dibuat role 'dosen' status ACTIVE (langsung aktif, tanpa persetujuan admin).
 *   Data instansi (perguruan tinggi/fakultas/departemen/prodi) dilengkapi setelah
 *   akun aktif, di halaman Profil (lihat ProfileController::updateProfile()).
 */
class RegisterController extends Controller
{
    public function showRegisterForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', 'in:mahasiswa,dosen'],
            'nidn' => ['nullable', 'string', 'max:20', 'unique:users,nidn'],
        ]);

        $role = $validated['role'] ?? 'mahasiswa';

        // Semua role langsung aktif (tanpa verifikasi email / persetujuan admin).
        $registrationStatus = 'active';

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'nidn' => $role === 'dosen' ? ($validated['nidn'] ?? null) : null,
            'registration_status' => $registrationStatus,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([$role]);

        // Login otomatis setelah registrasi.
        auth()->login($user);

        // Dosen: arahkan ke Profil untuk melengkapi data instansi setelah akun aktif.
        if ($role === 'dosen') {
            return redirect()->route('profile.index')
                ->with('success', 'Registrasi berhasil. Silakan lengkapi data instansi Anda.');
        }

        return redirect()->route('dashboard')
            ->with('success', 'Registrasi berhasil. Selamat datang!');
    }
}