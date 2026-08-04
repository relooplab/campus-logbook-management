<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Services\OrganizationalDirectoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Registrasi mandiri mahasiswa & dosen.
 * - Mahasiswa: akun dibuat role 'mahasiswa' status PENDING, lalu disetujui dosen.
 * - Dosen: akun dibuat role 'dosen' status PENDING, lalu disetujui admin.
 * Opsional (mahasiswa): centang "sebagai penguji" + isi nama pembimbing (maks 3).
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
            'as_examiner' => ['nullable', 'boolean'],
            // Nama pembimbing yang diuji (maks 3), hanya jika role mahasiswa & as_examiner.
            'supervisor_1' => ['nullable', 'string', 'max:255'],
            'supervisor_2' => ['nullable', 'string', 'max:255'],
            'supervisor_3' => ['nullable', 'string', 'max:255'],
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

        $supervisors = [];
        if ($role === 'mahasiswa' && $request->boolean('as_examiner')) {
            foreach (['supervisor_1', 'supervisor_2', 'supervisor_3'] as $f) {
                $v = trim((string) ($validated[$f] ?? ''));
                if ($v !== '') {
                    $supervisors[] = $v;
                }
            }
            $supervisors = array_slice($supervisors, 0, 3);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'nidn' => $role === 'dosen' ? ($validated['nidn'] ?? null) : null,
            'registration_status' => 'pending',
            'examiner_supervisor_names' => $supervisors ?: null,
        ]);
        $user->syncRoles([$role]);

        // Dosen: hubungkan ke direktori organisasi (pilih/create + dedup).
        if ($role === 'dosen' && !empty($validated['university_name'])) {
            $this->attachUniversity($user, $validated);
        }

        $approver = $role === 'dosen' ? 'admin' : 'dosen';

        // Beri tahu dosen (mode individual) saat ada mahasiswa baru mendaftar.
        if ($role === 'mahasiswa') {
            $this->bestEffort(fn () => $this->notifyDosenOfNewRegistration($user));
        }

        return redirect()->route('login')
            ->with('status', "Pendaftaran dikirim. Menunggu persetujuan {$approver}.");
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

    /**
     * Kirim notifikasi ke dosen (mode individual) bahwa ada mahasiswa baru.
     */
    private function notifyDosenOfNewRegistration(User $mahasiswa): void
    {
        $dosen = User::role('dosen')->where('registration_status', 'approved')->first();
        if (!$dosen) {
            return;
        }

        $dosen->notify(new ActivityNotification(
            "Mahasiswa baru '{$mahasiswa->name}' mendaftar dan menunggu persetujuan Anda.",
            route('approval.index'),
            'Pendaftaran Mahasiswa Baru'
        ));
    }
}
