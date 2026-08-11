<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Registrasi mandiri mahasiswa & dosen.
 * - Mahasiswa: akun dibuat role 'mahasiswa' status ACTIVE.
 * - Dosen: akun dibuat role 'dosen' status ACTIVE.
 *
 * Perilaku verifikasi email dikontrol oleh system admin via setting
 * `institutions.email_verification_required`:
 *   - OFF (default): auto-verify + auto-login (perilaku lama).
 *   - ON:  email_verified_at = null, auto-login, lalu middleware
 *          EnsureEmailVerified mengarahkan ke halaman verifikasi.
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
            // Identitas: NIDN untuk dosen, NIM untuk mahasiswa (unik lintas kolom).
            // NIM wajib untuk mahasiswa (konsisten dengan updateProfile).
            'nim' => ['required_if:role,mahasiswa', 'string', 'max:30', function ($attr, $value, $fail) {
                if ($value && User::identifierIsTaken($value)) {
                    $fail('NIM/NIDN ini sudah dipakai akun lain.');
                }
            }],
            'nidn' => ['nullable', 'string', 'max:20', function ($attr, $value, $fail) {
                if ($value && User::identifierIsTaken($value)) {
                    $fail('NIM/NIDN ini sudah dipakai akun lain.');
                }
            }],
        ]);

        $role = $validated['role'] ?? 'mahasiswa';
        $verificationRequired = (bool) Institution::query()->value('email_verification_required');

        // Semua role langsung aktif (tanpa persetujuan admin).
        $registrationStatus = 'active';

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            // Mahasiswa -> NIM; Dosen -> NIDN. Masing-masing di kolomnya sendiri.
            'nim' => $role === 'mahasiswa' ? ($validated['nim'] ?? null) : null,
            'nidn' => $role === 'dosen' ? ($validated['nidn'] ?? null) : null,
            'registration_status' => $registrationStatus,
            // Jika verifikasi email wajib, biarkan null agar user dipaksa
            // verifikasi sebelum bisa masuk fitur aplikasi.
            'email_verified_at' => $verificationRequired ? null : now(),
        ]);
        $user->syncRoles([$role]);

        // Login otomatis setelah registrasi (juga untuk kasus verifikasi wajib,
        // karena middleware akan mengarahkan ke halaman notice).
        auth()->login($user);

        // Kirim email verifikasi saat wajib. Aman dipanggil walau setting
        // berubah di tengah jalan — method ini no-op jika sudah verified.
        if ($verificationRequired && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        // Mahasiswa: langsung ke dashboard (isi profil & pilih dosen).
        // Dosen: langsung ke halaman afiliasi (wajib isi semua) sebelum fitur lain.
        if ($role === 'dosen') {
            return redirect()->route('profile.affiliation')
                ->with('success', 'Registrasi berhasil. Lengkapi afiliasi institusi Anda terlebih dahulu.');
        }

        return redirect()->route('dashboard')
            ->with('success', 'Registrasi berhasil. Selamat datang!');
    }

}