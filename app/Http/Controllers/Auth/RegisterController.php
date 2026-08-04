<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Registrasi mandiri mahasiswa & dosen.
 * - Mahasiswa: akun dibuat role 'mahasiswa' status ACTIVE (setelah verifikasi email),
 *   lalu memilih dosen pembimbing di profil.
 * - Dosen: akun dibuat role 'dosen' status PENDING, lalu disetujui admin.
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
            // Direktori organisasi (dosen).
            'nidn' => ['nullable', 'string', 'max:20', 'unique:users,nidn'],
            'university_name' => ['nullable', 'string', 'max:255'],
            'university_npsn' => ['nullable', 'string', 'max:20'],
            'faculty_name' => ['nullable', 'string', 'max:255'],
            'department_name' => ['nullable', 'string', 'max:255'],
            'study_program_name' => ['nullable', 'string', 'max:255'],
            'study_program_code' => ['nullable', 'string', 'max:20'],
        ]);

        $role = $validated['role'] ?? 'mahasiswa';

        // Alur baru: mahasiswa langsung aktif (verifikasi email), belum attach dosen.
        // Dosen tetap menunggu persetujuan admin (pending).
        $registrationStatus = $role === 'dosen' ? 'pending' : 'active';

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'nidn' => $role === 'dosen' ? ($validated['nidn'] ?? null) : null,
            'registration_status' => $registrationStatus,
        ]);
        $user->syncRoles([$role]);

        // Dosen: hubungkan ke direktori organisasi (pilih/create + dedup).
        if ($role === 'dosen' && !empty($validated['university_name'])) {
            $this->attachUniversity($user, $validated);
        }

        // Kirim email verifikasi (semua role) — bungkus agar kegagalan mail
        // tidak menggagalkan registrasi (mis. SMTP mati / tidak terkonfigurasi).
        $this->bestEffort(fn () => $user->sendEmailVerificationNotification());

        return redirect()->route('verification.notice')
            ->with('status', 'Registrasi berhasil. Silakan verifikasi email Anda.');
    }

    /**
     * Hubungkan dosen ke direktori organisasi (dedup via service).
     */
    private function attachUniversity(User $user, array $data): void
    {
        $service = app(OrganizationalDirectoryService::class);

        $university = $service->findOrCreateUniversity(
            $data['university_name'],
            $data['university_npsn'] ?? null
        );

        $faculty = null;
        $department = null;
        $studyProgram = null;

        if (!empty($data['faculty_name'])) {
            $faculty = $service->findOrCreateFaculty($university, $data['faculty_name']);
        }
        if ($faculty && !empty($data['department_name'])) {
            $department = $service->findOrCreateDepartment($faculty, $data['department_name']);
        }
        if ($department && !empty($data['study_program_name'])) {
            $studyProgram = $service->findOrCreateStudyProgram(
                $department,
                $data['study_program_name'],
                $data['study_program_code'] ?? null
            );
        }

        $service->attachUserToUniversity($user, $university, $faculty, $department, $studyProgram, true);
    }
}
