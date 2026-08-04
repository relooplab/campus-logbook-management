<?php

namespace App\Http\Controllers;

use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Form profil sendiri.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $programs = $user->allPrograms()->with(['pembimbing1', 'pembimbing2', 'members'])->get();

        return view('profile.index', ['user' => $user, 'programs' => $programs]);
    }

    /**
     * Perbarui data profil (nama, identifier, kontak, tautan akademik).
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'identifier' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'telegram' => ['nullable', 'string', 'max:60'],
            'linkedin' => ['nullable', 'url', 'max:255'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'], // 5 MB
        ];

        // Field akademik khusus dosen.
        if ($user->isDosen()) {
            $rules['google_scholar'] = ['nullable', 'url', 'max:255'];
            $rules['orcid'] = ['nullable', 'string', 'max:40'];
            $rules['sinta'] = ['nullable', 'string', 'max:40'];
            $rules['researchgate'] = ['nullable', 'url', 'max:255'];
            $rules['jadwal_bimbingan_url'] = ['nullable', 'url', 'max:255'];
        }

        $validated = $request->validate($rules);

        // Upload foto profil (disk 'public' agar dapat diakses via /storage).
        if ($request->hasFile('photo')) {
            if ($user->profile_photo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_photo_path);
            }
            $validated['profile_photo_path'] = $request->file('photo')->store('profiles', 'public');
        }

        $user->update($validated);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    /**
     * Perbarui judul TA / tempat KP (wajib diisi mahasiswa saat program aktif).
     * Hanya pemilik program yang dapat mengubah.
     */
    public function updateProgram(Request $request, MahasiswaTa $mahasiswaTa): RedirectResponse
    {
        $user = $request->user();

        abort_unless($mahasiswaTa->isMember($user), 403, 'Anda bukan anggota program ini.');

        $isKp = $mahasiswaTa->isKp();

        $validated = $request->validate([
            'judul_ta' => [$isKp ? 'nullable' : 'required', 'string', 'max:255'],
            'tempat_kp' => [$isKp ? 'required' : 'nullable', 'string', 'max:255'],
        ]);

        $mahasiswaTa->update($validated);

        return back()->with('success', 'Data '.($isKp ? 'KP' : 'TA').' berhasil diperbarui.');
    }

    /**
     * Ganti kata sandi.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Kata sandi saat ini salah.']);
        }

        $user->update(['password' => $validated['password']]);

        return back()->with('success', 'Kata sandi berhasil diubah.');
    }

    /**
     * Lihat profil user lain — hanya jika ada hubungan langsung
     * (pembimbing/penguji/mahasiswa bimbingan, TA bersama, atau grup yang sama).
     */
    public function show(Request $request, User $user): View
    {
        $viewer = $request->user();

        // Admin selalu bisa melihat profil.
        if ($viewer->isAdmin()) {
            return $this->renderProfile($user);
        }

        // Lihat profil sendiri.
        if ($viewer->id === $user->id) {
            return $this->renderProfile($user);
        }

        // Gunakan aturan "hanya hubungan langsung".
        abort_unless($viewer->hasDirectRelation($user), 403, 'Anda tidak memiliki hubungan langsung dengan pengguna ini.');

        return $this->renderProfile($user);
    }

    /**
     * Render halaman profil dengan data pengguna.
     */
    private function renderProfile(User $user): View
    {
        $user->load('mahasiswaTa');

        return view('profile.show', ['profile' => $user]);
    }
}
