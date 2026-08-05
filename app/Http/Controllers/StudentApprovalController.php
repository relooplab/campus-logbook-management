<?php

namespace App\Http\Controllers;

use App\Models\MahasiswaTa;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use App\Support\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Persetujuan attachment dosen (alur baru).
 * Mahasiswa aktif memilih dosen → MahasiswaTa status pending_approval.
 * Dosen menyetujui/tolak, dan bisa mengubah peran (pembimbing/penguji).
 */
class StudentApprovalController extends Controller
{
    /**
     * Daftar permintaan attachment yang menunggu persetujuan dosen ini.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Mahasiswa yang memilih dosen ini sebagai pembimbing/penguji, status pending_approval.
        $pending = MahasiswaTa::where('status_ta', MahasiswaTa::STATUS_PENDING_APPROVAL)
            ->where(function ($q) use ($user) {
                $q->where('pembimbing_1_id', $user->id)
                    ->orWhere('pembimbing_2_id', $user->id)
                    ->orWhere('penguji_1_id', $user->id)
                    ->orWhere('penguji_2_id', $user->id);
            })
            ->with(['mahasiswa', 'pembimbing1', 'pembimbing2', 'penguji1', 'penguji2'])
            ->orderBy('created_at')
            ->get();

        return view('approval.index', compact('pending'));
    }

    /**
     * Tambah mahasiswa manual oleh dosen (mode individual) — hanya input email.
     * Akun dibuat status active (verifikasi email menyusul).
     */
    public function invite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
        ]);

        $email = strtolower(trim($validated['email']));
        $local = strtok($email, '@') ?: 'Mahasiswa';
        $name = ucwords(str_replace(['.', '_', '-'], ' ', $local));
        $password = Str::random(10);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'registration_status' => 'active',
            'institution_id' => Feature::isInstitution() ? $request->user()->institution_id : null,
        ]);
        $user->syncRoles(['mahasiswa']);

        // Mahasiswa yang di-invite otomatis mengikuti institusi dosen.
        $this->copyUniversityToStudent($request->user(), $user);

        return redirect()->route('approval.index')
            ->with('success', "Mahasiswa '{$name}' ditambahkan. Mahasiswa perlu verifikasi email & memilih dosen.");
    }

    /**
     * Setujui permintaan attachment — MahasiswaTa jadi aktif, mahasiswa jadi verified.
     * Dosen bisa mengubah peran (pembimbing/penguji) saat menyetujui.
     */
    public function approve(Request $request, MahasiswaTa $mahasiswaTa): RedirectResponse
    {
        $dosen = $request->user();

        // Pastikan dosen ini terkait dengan MahasiswaTa tersebut.
        abort_unless($mahasiswaTa->isPembimbing($dosen) || $mahasiswaTa->isPenguji($dosen), 403, 'Anda tidak terkait dengan program ini.');
        abort_unless($mahasiswaTa->status_ta === MahasiswaTa::STATUS_PENDING_APPROVAL, 400, 'Status program bukan pending approval.');

        $faseKeys = array_keys($mahasiswaTa->isKp() ? MahasiswaTa::FASES_KP : MahasiswaTa::FASES);

        $validated = $request->validate([
            'judul_ta' => ['nullable', 'string', 'max:255'],
            'tempat_kp' => ['nullable', 'string', 'max:255'],
            'role_dosen' => ['required', 'in:pembimbing_1,pembimbing_2,penguji_1,penguji_2'],
            'target_sesi' => ['nullable', 'integer', 'min:1'],
            'fase' => ['nullable', 'in:'.implode(',', $faseKeys)],
        ]);

        // Map peran ke kolom sebenarnya.
        $roleColumn = [
            'pembimbing_1' => 'pembimbing_1_id',
            'pembimbing_2' => 'pembimbing_2_id',
            'penguji_1' => 'penguji_1_id',
            'penguji_2' => 'penguji_2_id',
        ][$validated['role_dosen']];

        // Kosongkan peran dosen ini di semua kolom, lalu set ke peran baru.
        $update = [
            'judul_ta' => $validated['judul_ta'] ?? $mahasiswaTa->judul_ta,
            'tempat_kp' => $validated['tempat_kp'] ?? $mahasiswaTa->tempat_kp,
            'target_sesi' => $validated['target_sesi'] ?? $mahasiswaTa->target_sesi ?? 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
            'fase' => $validated['fase'] ?? $mahasiswaTa->fase,
        ];
        foreach (['pembimbing_1_id', 'pembimbing_2_id', 'penguji_1_id', 'penguji_2_id'] as $col) {
            if ($mahasiswaTa->{$col} === $dosen->id) {
                $update[$col] = null;
            }
        }
        $update[$roleColumn] = $dosen->id;
        $mahasiswaTa->update($update);

        // Mahasiswa jadi verified.
        $mahasiswaTa->mahasiswa?->update(['registration_status' => 'verified']);

        // Mahasiswa yang disetujui otomatis mengikuti institusi dosen.
        if ($mahasiswaTa->mahasiswa) {
            $this->copyUniversityToStudent($dosen, $mahasiswaTa->mahasiswa);
        }

        return redirect()->route('approval.index')
            ->with('success', "Mahasiswa '{$mahasiswaTa->mahasiswa?->name}' disetujui sebagai ".str_replace('_', ' ', $validated['role_dosen']).'.');
    }

    /**
     * Tolak permintaan attachment — MahasiswaTa jadi ditolak, mahasiswa bisa pilih dosen lagi.
     */
    public function reject(Request $request, MahasiswaTa $mahasiswaTa): RedirectResponse
    {
        $dosen = $request->user();

        // Pastikan dosen ini terkait dengan MahasiswaTa tersebut.
        abort_unless($mahasiswaTa->isPembimbing($dosen) || $mahasiswaTa->isPenguji($dosen), 403, 'Anda tidak terkait dengan program ini.');
        abort_unless($mahasiswaTa->status_ta === MahasiswaTa::STATUS_PENDING_APPROVAL, 400, 'Status program bukan pending approval.');

        $mahasiswaTa->update(['status_ta' => MahasiswaTa::STATUS_DITOLAK]);

        return redirect()->route('approval.index')
            ->with('success', "Permintaan '{$mahasiswaTa->mahasiswa?->name}' ditolak. Mahasiswa dapat memilih dosen lain.");
    }

    /**
     * Salin universitas (direktori) dari dosen ke mahasiswa.
     *
     * Mahasiswa HANYA boleh punya 1 afiliasi — jadi semua afiliasi lama
     * dihapus terlebih dahulu ($replaceAll = true), lalu di-set ke afiliasi
     * dosen. Dosen sendiri tetap boleh multi-afiliasi.
     */
    private function copyUniversityToStudent(User $dosen, User $mahasiswa): void
    {
        $primary = $dosen->primaryUniversity();
        if (!$primary) {
            return;
        }

        $service = app(OrganizationalDirectoryService::class);

        $pivot = $dosen->universities()
            ->where('university_id', $primary->id)
            ->first();

        $service->attachUserToUniversity(
            $mahasiswa,
            $primary,
            $pivot?->pivot->faculty_id ? \App\Models\Faculty::find($pivot->pivot->faculty_id) : null,
            $pivot?->pivot->department_id ? \App\Models\Department::find($pivot->pivot->department_id) : null,
            $pivot?->pivot->study_program_id ? \App\Models\StudyProgram::find($pivot->pivot->study_program_id) : null,
            true,
            true // $replaceAll — mahasiswa hanya 1 afiliasi
        );
    }
}