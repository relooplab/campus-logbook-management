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

        // Identifier (NIM) & WhatsApp wajib untuk mahasiswa.
        if ($user->isMahasiswa()) {
            $rules['identifier'] = ['required', 'string', 'max:30'];
            $rules['whatsapp'] = ['required', 'string', 'max:30'];
        }

        // Field akademik khusus dosen.
        if ($user->isDosen()) {
            $rules['google_scholar'] = ['nullable', 'url', 'max:255'];
            $rules['orcid'] = ['nullable', 'string', 'max:40'];
            $rules['sinta'] = ['nullable', 'string', 'max:40'];
            $rules['researchgate'] = ['nullable', 'url', 'max:255'];
            $rules['jadwal_bimbingan_url'] = ['nullable', 'url', 'max:255'];
            $rules['bimbingan_via_whatsapp'] = ['nullable', 'boolean'];
            $rules['bimbingan_via_telegram'] = ['nullable', 'boolean'];
        }

        $validated = $request->validate($rules);

        // Konversi nilai checkbox opt-in jalur bimbingan (khusus dosen).
        if ($user->isDosen()) {
            $validated['bimbingan_via_whatsapp'] = $request->boolean('bimbingan_via_whatsapp');
            $validated['bimbingan_via_telegram'] = $request->boolean('bimbingan_via_telegram');
        }

        // Upload foto profil (disk 'public' agar dapat diakses via /storage).
        if ($request->hasFile('photo')) {
            if ($user->profile_photo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_photo_path);
            }
            $validated['profile_photo_path'] = $request->file('photo')->store('profiles', 'public');
        }

        $user->update($validated);

        // Mahasiswa diarahkan ke dashboard agar langsung bisa memilih dosen.
        if ($user->isMahasiswa()) {
            return redirect()->route('dashboard')
                ->with('success', 'Profil berhasil diperbarui. Silakan pilih dosen pembimbing.');
        }

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
     * Form pilih dosen (mahasiswa aktif yang belum attach dosen).
     * Menampilkan daftar dosen untuk dipilih sebagai pembimbing/penguji.
     */
    public function selectDosen(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isMahasiswa(), 403);

        // Wajib isi profil dulu (NIM & WhatsApp) sebelum memilih dosen.
        if (blank($user->identifier) || blank($user->whatsapp)) {
            return redirect()->route('profile.index')
                ->with('warning', 'Lengkapi profil Anda (NIM & WhatsApp) terlebih dahulu sebelum memilih dosen.');
        }

        // Daftar dosen yang aktif (registration_status = active).
        $dosenList = \App\Models\User::role('dosen')
            ->where('registration_status', 'active')
            ->orderBy('name')
            ->get();

        // Label fase kustom berdasarkan afiliasi mahasiswa (prodi/departemen).
        $affiliation = $user->universities()
            ->orderByDesc('user_university.is_primary')
            ->first();
        $namingService = app(\App\Services\ProgramNamingService::class);
        $faseLabelsTa = $namingService->faseLabelsFor(
            $user->institution_id,
            \App\Models\MahasiswaTa::JENIS_TA,
            $affiliation?->pivot->study_program_id,
            $affiliation?->pivot->department_id
        );
        $faseLabelsKp = $namingService->faseLabelsFor(
            $user->institution_id,
            \App\Models\MahasiswaTa::JENIS_KP,
            $affiliation?->pivot->study_program_id,
            $affiliation?->pivot->department_id
        );
        $jenisLabelTa = $namingService->jenisLabelFor(
            $user->institution_id,
            \App\Models\MahasiswaTa::JENIS_TA,
            $affiliation?->pivot->study_program_id,
            $affiliation?->pivot->department_id
        );
        $jenisLabelKp = $namingService->jenisLabelFor(
            $user->institution_id,
            \App\Models\MahasiswaTa::JENIS_KP,
            $affiliation?->pivot->study_program_id,
            $affiliation?->pivot->department_id
        );

        return view('profile.select-dosen', compact('user', 'dosenList', 'faseLabelsTa', 'faseLabelsKp', 'jenisLabelTa', 'jenisLabelKp'));
    }

    /**
     * Simpan pilihan dosen — buat MahasiswaTa dengan status pending_approval.
     * Mahasiswa bisa memilih dosen sebagai pembimbing/penguji.
     */
    public function storeDosen(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isMahasiswa(), 403);

        // Wajib isi profil dulu (NIM & WhatsApp) sebelum memilih dosen.
        if (blank($user->identifier) || blank($user->whatsapp)) {
            return redirect()->route('profile.index')
                ->with('warning', 'Lengkapi profil Anda (NIM & WhatsApp) terlebih dahulu sebelum memilih dosen.');
        }

        $jenis = $request->input('jenis');
        $faseKeys = array_keys($jenis === 'kp' ? \App\Models\MahasiswaTa::FASES_KP : \App\Models\MahasiswaTa::FASES);

        $validated = $request->validate([
            'jenis' => ['required', 'in:ta,kp'],
            'fase' => ['required', 'in:'.implode(',', $faseKeys)],
            'pembimbing_1_id' => ['required', 'exists:users,id'],
            'pembimbing_2_id' => ['nullable', 'exists:users,id'],
            'penguji_1_id' => ['nullable', 'exists:users,id'],
            'penguji_2_id' => ['nullable', 'exists:users,id'],
        ]);

        // Pastikan dosen yang dipilih benar-benar dosen aktif.
        $dosenIds = array_filter([
            $validated['pembimbing_1_id'],
            $validated['pembimbing_2_id'] ?? null,
            $validated['penguji_1_id'] ?? null,
            $validated['penguji_2_id'] ?? null,
        ]);
        $validDosen = \App\Models\User::role('dosen')
            ->where('registration_status', 'active')
            ->whereIn('id', $dosenIds)
            ->count();
        abort_unless($validDosen === count($dosenIds), 422, 'Dosen yang dipilih tidak valid.');

        // Cegah duplikat program (satu TA + satu KP per mahasiswa) — program yang
        // sudah ditolak tidak dihitung, agar mahasiswa bisa memilih dosen lain.
        $exists = $user->mahasiswaPrograms()
            ->where('jenis', $validated['jenis'])
            ->where('status_ta', '!=', \App\Models\MahasiswaTa::STATUS_DITOLAK)
            ->exists();
        abort_if($exists, 422, 'Anda sudah memiliki program '.strtoupper($validated['jenis']).'.');

        $ta = \App\Models\MahasiswaTa::create([
            'user_id' => $user->id,
            'jenis' => $validated['jenis'],
            'pembimbing_1_id' => $validated['pembimbing_1_id'],
            'pembimbing_2_id' => $validated['pembimbing_2_id'] ?? null,
            'penguji_1_id' => $validated['penguji_1_id'] ?? null,
            'penguji_2_id' => $validated['penguji_2_id'] ?? null,
            'target_sesi' => 7,
            'status_ta' => \App\Models\MahasiswaTa::STATUS_PENDING_APPROVAL,
            'fase' => $validated['fase'],
        ]);

        // Notifikasi ke dosen yang dipilih.
        foreach ($dosenIds as $dosenId) {
            if ($dosen = \App\Models\User::find($dosenId)) {
                $this->bestEffort(fn () => $dosen->notify(new \App\Notifications\ActivityNotification(
                    "Mahasiswa '{$user->name}' memilih Anda sebagai dosen untuk program ".strtoupper($validated['jenis']).'.',
                    route('approval.index'),
                    'Permintaan Attachment Dosen',
                )));
            }
        }

        return redirect()->route('profile.index')
            ->with('success', 'Permintaan attachment dosen dikirim. Menunggu persetujuan dosen.');
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

        // Admin selalu bisa melihat profil — tapi admin biasa di mode institusi
        // hanya boleh melihat user di institusinya sendiri (system_admin tetap
        // platform-level, bisa lihat semua).
        if ($viewer->isAdmin()) {
            if (!$viewer->isSystemAdmin()
                && $viewer->institution_id !== null
                && $user->institution_id !== $viewer->institution_id) {
                abort(403, 'Anda tidak memiliki hubungan langsung dengan pengguna ini.');
            }

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
