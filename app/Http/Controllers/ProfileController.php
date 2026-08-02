<?php

namespace App\Http\Controllers;

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
        return view('profile.index', ['user' => $request->user()]);
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
     * Lihat profil user lain (mahasiswa -> dosen, dosen/admin -> mahasiswa, dll).
     */
    public function show(Request $request, User $user): View
    {
        // Semua user login boleh melihat profil pengguna lain.
        $user->load('mahasiswaTa');

        return view('profile.show', ['profile' => $user]);
    }
}
