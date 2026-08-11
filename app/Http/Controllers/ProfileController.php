<?php

namespace App\Http\Controllers;

use App\Models\MahasiswaTa;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use App\Support\Feature;
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

        // Direktori untuk card afiliasi mahasiswa (pilih dari data yang sudah ada).
        $universities = University::orderBy('name')->with('faculties.departments.studyPrograms')->get();
        $affiliation = $user->primaryUniversity();

        return view('profile.index', compact('user', 'programs', 'universities', 'affiliation'));
    }

    /**
     * Perbarui data profil (nama, nim, kontak, tautan akademik).
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'nim' => ['nullable', 'string', 'max:30', function ($attr, $value, $fail) use ($user) {
                if ($value && \App\Models\User::identifierIsTaken($value, $user->id)) {
                    $fail('NIM/NIDN ini sudah dipakai akun lain.');
                }
            }],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'telegram' => ['nullable', 'string', 'max:60'],
            'linkedin' => ['nullable', 'url', 'max:255'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'], // 5 MB
        ];

        // Identifier (NIM) & WhatsApp wajib untuk mahasiswa.
        if ($user->isMahasiswa()) {
            $rules['nim'] = ['required', 'string', 'max:30'];
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
        if (blank($user->nim) || blank($user->whatsapp)) {
            return redirect()->route('profile.index')
                ->with('warning', 'Lengkapi profil Anda (NIM & WhatsApp) terlebih dahulu sebelum memilih dosen.');
        }

        // Afiliasi mahasiswa wajib terisi sampai tingkat prodi — menjadi filter
        // pencarian dosen pada langkah berikutnya.
        $affiliation = $user->universities()
            ->orderByDesc('user_university.is_primary')
            ->first();
        if (! $affiliation?->pivot?->study_program_id) {
            return redirect()->route('profile.index')
                ->with('warning', 'Lengkapi afiliasi perguruan tinggi Anda (sampai program studi) terlebih dahulu sebelum memilih dosen.');
        }

        // Daftar dosen aktif — difilter oleh perguruan tinggi mahasiswa.
        $dosenList = \App\Models\User::role('dosen')
            ->where('registration_status', 'active')
            ->whereHas('universities', fn ($q) => $q->whereKey($affiliation->id))
            ->orderBy('name')
            ->get();
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

        return view('profile.select-dosen', compact('user', 'affiliation', 'dosenList', 'faseLabelsTa', 'faseLabelsKp', 'jenisLabelTa', 'jenisLabelKp'));
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
        if (blank($user->nim) || blank($user->whatsapp)) {
            return redirect()->route('profile.index')
                ->with('warning', 'Lengkapi profil Anda (NIM & WhatsApp) terlebih dahulu sebelum memilih dosen.');
        }

        // Wajib afiliasi perguruan tinggi (sampai prodi) sebelum memilih dosen.
        $affiliation = $user->universities()
            ->orderByDesc('user_university.is_primary')
            ->first();
        if (! $affiliation?->pivot?->study_program_id) {
            return redirect()->route('profile.index')
                ->with('warning', 'Lengkapi afiliasi perguruan tinggi Anda (sampai program studi) terlebih dahulu sebelum memilih dosen.');
        }

        $jenis = $request->input('jenis');
        $faseKeys = array_keys($jenis === 'kp' ? \App\Models\MahasiswaTa::FASES_KP : \App\Models\MahasiswaTa::FASES);

        $validated = $request->validate([
            'jenis' => ['required', 'in:ta,kp'],
            'fase' => ['required', 'in:'.implode(',', $faseKeys)],
            // Mahasiswa wajib mengisi SALAH SATU dari 4 peran dosen
            // (pembimbing 1/2 atau penguji 1/2). Boleh pilih 1–4 peran;
            // yang penting minimal satu dosen ter-assign.
            'pembimbing_1_id' => ['nullable', 'required_without_all:pembimbing_2_id,penguji_1_id,penguji_2_id', 'exists:users,id'],
            'pembimbing_2_id' => ['nullable', 'exists:users,id'],
            'penguji_1_id' => ['nullable', 'exists:users,id'],
            'penguji_2_id' => ['nullable', 'exists:users,id'],
        ], [
            'pembimbing_1_id.required_without_all' => 'Pilih minimal satu peran dosen (pembimbing atau penguji).',
        ]);

        // Pastikan dosen yang dipilih benar-benar dosen aktif.
        $dosenIds = array_filter([
            $validated['pembimbing_1_id'],
            $validated['pembimbing_2_id'] ?? null,
            $validated['penguji_1_id'] ?? null,
            $validated['penguji_2_id'] ?? null,
        ]);

        // Cegah dosen yang sama dipilih lebih dari satu peran.
        abort_unless(count($dosenIds) === count(array_unique($dosenIds)), 422, 'Satu dosen tidak boleh dipilih di lebih dari satu peran (pembimbing/penguji).');

        $validDosen = \App\Models\User::role('dosen')
            ->where('registration_status', 'active')
            ->whereHas('universities', fn ($q) => $q->whereKey($affiliation->id))
            ->whereIn('id', $dosenIds)
            ->count();
        abort_unless($validDosen === count($dosenIds), 422, 'Salah satu dosen yang dipilih tidak valid atau bukan dari perguruan tinggi yang sama.');

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

        return redirect()->route('dashboard')
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
    /**
     * Form kelola afiliasi (dosen) — daftar afiliasi + status & form tambah/ubah.
     */
    public function affiliation(Request $request): View
    {
        $user = $request->user();

        $universities = $user->universities()->get()->map(function ($univ) {
            $p = $univ->pivot;

            return [
                'university' => $univ,
                'faculty' => $p->faculty_id ? \App\Models\Faculty::find($p->faculty_id) : null,
                'department' => $p->department_id ? \App\Models\Department::find($p->department_id) : null,
                'study_program' => $p->study_program_id ? \App\Models\StudyProgram::find($p->study_program_id) : null,
                'is_primary' => (bool) $p->is_primary,
                'status' => $p->status ?: OrganizationalDirectoryService::STATUS_ACTIVE,
            ];
        });

        return view('profile.affiliation', ['user' => $user, 'affiliations' => $universities]);
    }

    /**
     * Tambah/ubah afiliasi dosen ke direktori organisasi.
     *
     * - Node (prodi) tdk berlangganan → afiliasi langsung `active` (aman).
     * - Node berlangganan → `pending` + notifikasi admin level terendah; akses
     *   ke Workspace Institusi baru muncul setelah disetujui.
     */
    public function updateAffiliation(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isDosen(), 403, 'Hanya dosen yang dapat mengubah afiliasi.');

        $validated = $request->validate([
            'university_name' => ['required', 'string', 'max:255'],
            'faculty_name' => ['required', 'string', 'max:255'],
            'department_name' => ['required', 'string', 'max:255'],
            'study_program_name' => ['required', 'string', 'max:255'],
        ]);

        $service = app(OrganizationalDirectoryService::class);

        $university = $service->findOrCreateUniversity($validated['university_name']);

        $faculty = null;
        $department = null;
        $studyProgram = null;

        if (!empty($validated['faculty_name'])) {
            $faculty = $service->findOrCreateFaculty($university, $validated['faculty_name']);
        }
        if ($faculty && !empty($validated['department_name'])) {
            $department = $service->findOrCreateDepartment($faculty, $validated['department_name']);
        }
        if ($department && !empty($validated['study_program_name'])) {
            $studyProgram = $service->findOrCreateStudyProgram($department, $validated['study_program_name']);
        }

        // Gate approval: node prodi berlangganan → harus pending.
        $status = OrganizationalDirectoryService::STATUS_ACTIVE;
        if ($studyProgram && Feature::directorySubscriptionActive('study_program', $studyProgram->id)) {
            $status = OrganizationalDirectoryService::STATUS_PENDING;
        }

        $service->attachUserToUniversity(
            $user, $university, $faculty, $department, $studyProgram,
            true, false, $status
        );
        // Jadikan afiliasi ini primer (yang lain non-primer).
        $service->setPrimaryUniversity($user, $university);

        if ($status === OrganizationalDirectoryService::STATUS_PENDING) {
            $approvers = $service->lowestLevelAdminsForStudyProgram($studyProgram);
            foreach ($approvers as $approver) {
                $this->bestEffort(fn () => $approver->notify(new \App\Notifications\ActivityNotification(
                    "Dosen '{$user->name}' meminta bergabung ke institusi (prodi '{$studyProgram->name}').",
                    route('affiliation-approval.index'),
                    'Permintaan Persetujuan Afiliasi Dosen',
                )));
            }

            return redirect()->route('profile.index')
                ->with('success', 'Afiliasi diajukan ke admin institusi. Akses Workspace Institusi aktif setelah disetujui.');
        }

        return redirect()->route('profile.index')
            ->with('success', 'Afiliasi berhasil disimpan. Silakan lengkapi data profil Anda.');
    }

    /**
     * Simpan afiliasi mahasiswa: perguruan tinggi → fakultas → departemen → prodi.
     * Semua dipilih dari direktori yang sudah ada (tidak membuat data baru).
     */
    public function updateMahasiswaAffiliation(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isMahasiswa(), 403, 'Hanya mahasiswa yang dapat mengisi afiliasi.');

        $validated = $request->validate([
            'university_id' => ['required', 'exists:universities,id'],
            'faculty_id' => ['required', 'exists:faculties,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'study_program_id' => ['required', 'exists:study_programs,id'],
        ]);

        $faculty = \App\Models\Faculty::where('id', $validated['faculty_id'])
            ->where('university_id', $validated['university_id'])
            ->first();
        $department = $faculty
            ? \App\Models\Department::where('id', $validated['department_id'])
                ->where('faculty_id', $faculty->id)->first()
            : null;
        $studyProgram = $department
            ? \App\Models\StudyProgram::where('id', $validated['study_program_id'])
                ->where('department_id', $department->id)->first()
            : null;

        abort_unless($faculty && $department && $studyProgram, 422, 'Hierarki afiliasi tidak valid.');

        $university = University::findOrFail($validated['university_id']);
        $service = app(OrganizationalDirectoryService::class);
        $service->attachUserToUniversity(
            $user, $university, $faculty, $department, $studyProgram,
            isPrimary: true,
            replaceAll: true,
            status: OrganizationalDirectoryService::STATUS_ACTIVE
        );
        $service->setPrimaryUniversity($user, $university);

        return redirect()->route('profile.index')
            ->with('success', 'Afiliasi perguruan tinggi berhasil disimpan.');
    }

    /**
     * Cabut afiliasi — akses ke Workspace Institusi dari afiliasi tsb otomatis hilang.
     */
    public function revokeAffiliation(Request $request, University $university): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isDosen(), 403, 'Hanya dosen yang dapat mencabut afiliasi.');

        app(OrganizationalDirectoryService::class)->revokeAffiliation($user, $university);

        return redirect()->route('profile.affiliation')
            ->with('success', 'Afiliasi dicabut. Akses Workspace Institusi terkait telah dihapus.');
    }


    private function renderProfile(User $user): View
    {
        $user->load('mahasiswaTa');

        return view('profile.show', ['profile' => $user]);
    }
}
