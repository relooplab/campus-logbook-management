<?php

namespace App\Http\Controllers;

use App\Models\MahasiswaTa;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Daftar & persetujuan registrasi mahasiswa (mode individual).
 * Dosen menyetujui akun mahasiswa & menetapkan perannya.
 */
class StudentApprovalController extends Controller
{
    /**
     * Daftar mahasiswa pending registrasi.
     */
    public function index(): View
    {
        $pending = User::role('mahasiswa')
            ->where('registration_status', 'pending')
            ->orderBy('created_at')
            ->get();

        return view('approval.index', compact('pending'));
    }

    /**
     * Setujui mahasiswa & assign peran (pembimbing/penguji).
     */
    public function approve(Request $request, User $mahasiswa): RedirectResponse
    {
        $validated = $request->validate([
            'judul_ta' => ['nullable', 'string', 'max:255'],
            'role_dosen' => ['required', 'in:pembimbing_1,pembimbing_2,penguji_1,penguji_2'],
            'target_sesi' => ['nullable', 'integer', 'min:1'],
            'allow_examiner' => ['nullable', 'boolean'],
        ]);

        abort_if($mahasiswa->registration_status !== 'pending', 400, 'Status mahasiswa bukan pending.');

        $dosen = $request->user();

        // Map peran ke kolom sebenarnya (pembimbing_1 -> pembimbing_1_id).
        $roleColumn = [
            'pembimbing_1' => 'pembimbing_1_id',
            'pembimbing_2' => 'pembimbing_2_id',
            'penguji_1' => 'penguji_1_id',
            'penguji_2' => 'penguji_2_id',
        ][$validated['role_dosen']];

        // Buat data TA & assign peran dosen.
        $ta = MahasiswaTa::updateOrCreate(
            ['user_id' => $mahasiswa->id, 'jenis' => MahasiswaTa::JENIS_TA],
            [
                'judul_ta' => $validated['judul_ta'],
                $roleColumn => $dosen->id,
                'target_sesi' => $validated['target_sesi'] ?? 7,
                'institution_id' => Feature::isInstitution() ? $dosen->institution_id : null,
            ]
        );

        // Setujui akun mahasiswa.
        $mahasiswa->update(['registration_status' => 'approved']);

        // Jika mahasiswa mencentang "sebagai penguji" & disetujui dosen -> aktifkan.
        if ($request->boolean('allow_examiner') && !$mahasiswa->hasRole('dosen')) {
            // Peran penguji diwakili flag examinable (tanpa menambah role dosen).
            $mahasiswa->update(['examiner_supervisor_names' => $mahasiswa->examiner_supervisor_names ?: []]);
        }

        return redirect()->route('approval.index')
            ->with('success', "Mahasiswa '{$mahasiswa->name}' disetujui.");
    }

    /**
     * Tolak registrasi mahasiswa.
     */
    public function reject(User $mahasiswa): RedirectResponse
    {
        $mahasiswa->update(['registration_status' => 'rejected']);

        return redirect()->route('approval.index')
            ->with('success', "Registrasi '{$mahasiswa->name}' ditolak.");
    }
}
