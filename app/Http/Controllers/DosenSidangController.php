<?php

namespace App\Http\Controllers;

use App\Models\MahasiswaTa;
use App\Models\SeminarSubmission;
use App\Models\Sidang;
use App\Models\SidangGrade;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Riwayat Sidang — satu tempat mencatat sidang/seminar.
 * Dosen pembimbing & penguji sama-sama memberi nilai (via tabel sidang_grades).
 */
class DosenSidangController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Sidang tempat dosen terlibat: sebagai penguji, pembimbing TA, atau penilai.
        $taIds = MahasiswaTa::where('pembimbing_1_id', $user->id)
            ->orWhere('pembimbing_2_id', $user->id)
            ->pluck('id');

        $sidangs = Sidang::where(function ($q) use ($user, $taIds) {
            $q->where('penguji_id', $user->id)
                ->orWhereIn('mahasiswa_ta_id', $taIds)
                ->orWhereHas('grades', fn ($g) => $g->where('user_id', $user->id));
        })
            ->with(['mahasiswaTa.mahasiswa', 'grades.user', 'penguji'])
            ->orderByDesc('tanggal')
            ->get();

        $bimbingan = MahasiswaTa::where('pembimbing_1_id', $user->id)
            ->orWhere('pembimbing_2_id', $user->id)
            ->with('mahasiswa')
            ->get();

        // Dosen aktif untuk dipilih sebagai penguji (field "Penguji").
        $dosenList = User::role('dosen')->where('registration_status', 'active')->orderBy('name')->get();

        // Pre-fill dari bahan seminar (opsional ?submission=id).
        $preselect = null;
        if ($request->filled('submission')) {
            $preselect = SeminarSubmission::with('mahasiswaTa.mahasiswa')->find($request->integer('submission'));
        }

        // Grade milik dosen ini (terisi/belum) — untuk form nilai.
        $myGrades = SidangGrade::where('user_id', $user->id)
            ->with('sidang.mahasiswaTa.mahasiswa')
            ->orderByDesc('id')
            ->get();

        return view('dosen-sidang.index', compact('sidangs', 'bimbingan', 'dosenList', 'preselect', 'myGrades'));
    }

    /**
     * Catat riwayat sidang/seminar (satu form).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'mahasiswa_ta_id' => ['nullable', 'exists:mahasiswa_ta,id'],
            'mahasiswa_name' => ['nullable', 'string', 'max:255'],
            'jenis' => ['required', 'in:'.implode(',', Sidang::JENISES)],
            'tanggal' => ['required', 'date'],
            'hasil' => ['nullable', 'in:'.implode(',', Sidang::HASILS)],
            'penguji_name' => ['required', 'string', 'max:255'],
            'penguji_id' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_1' => ['nullable', 'string', 'max:255'],
            'supervisor_2' => ['nullable', 'string', 'max:255'],
            'supervisor_3' => ['nullable', 'string', 'max:255'],
            'submission_id' => ['nullable', 'integer', 'exists:seminar_submissions,id'],
        ]);

        if (empty($validated['mahasiswa_ta_id']) && empty($validated['mahasiswa_name'])) {
            return back()->withErrors(['mahasiswa' => 'Pilih mahasiswa bimbingan atau isi nama mahasiswa.'])->withInput();
        }

        $ta = $validated['mahasiswa_ta_id']
            ? MahasiswaTa::with(['pembimbing1', 'pembimbing2'])->find($validated['mahasiswa_ta_id'])
            : null;

        // Penguji: link dosen internal (pilih ATAU nama cocok), selain itu nama eksternal.
        $penguji = null;
        if (! empty($validated['penguji_id'])) {
            $penguji = User::find($validated['penguji_id']);
        } elseif ($ta) {
            $penguji = User::role('dosen')->where('name', $validated['penguji_name'])->first();
        }

        $supervisors = [];
        foreach (['supervisor_1', 'supervisor_2', 'supervisor_3'] as $f) {
            $v = trim((string) ($validated[$f] ?? ''));
            if ($v !== '') {
                $supervisors[] = $v;
            }
        }
        if (! $supervisors && $ta) {
            $supervisors = array_values(array_filter([$ta->pembimbing1?->name, $ta->pembimbing2?->name]));
        }

        $sidang = Sidang::create([
            'institution_id' => $user->institution_id,
            'mahasiswa_ta_id' => $validated['mahasiswa_ta_id'] ?? null,
            'mahasiswa_name' => $validated['mahasiswa_name'] ?? null,
            'penguji_id' => $penguji?->id,
            'penguji_name' => $validated['penguji_name'],
            'jenis' => $validated['jenis'],
            'tanggal' => $validated['tanggal'],
            'hasil' => $validated['hasil'] ?? null,
            'supervisor_names' => $supervisors ?: null,
        ]);

        // Buat target nilai bagi pembimbing & penguji internal.
        $this->createGradeTargets($sidang, $ta, $penguji);

        // Jika berasal dari bahan seminar, kaitkan ke submission.
        if (! empty($validated['submission_id'])) {
            SeminarSubmission::whereKey($validated['submission_id'])->update(['sidang_id' => $sidang->id]);
        }

        return redirect()->route('dosen-sidang.index')
            ->with('success', 'Riwayat sidang dicatat. Dosen terkait akan mengisi nilai.');
    }

    /**
     * Dosen penilai mengisi nilai.
     */
    public function grade(Request $request, Sidang $sidang): RedirectResponse
    {
        $user = $request->user();
        $grade = SidangGrade::where('sidang_id', $sidang->id)->where('user_id', $user->id)->first();
        abort_unless($grade, 403, 'Anda tidak ditugaskan memberi nilai pada sidang ini.');

        $validated = $request->validate([
            'nilai' => ['required', 'numeric', 'min:0', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $grade->update([
            'nilai' => $validated['nilai'],
            'catatan' => $validated['catatan'] ?? null,
            'filled_at' => now(),
        ]);

        return back()->with('success', 'Nilai berhasil disimpan.');
    }

    public function destroy(Request $request, Sidang $sidang): RedirectResponse
    {
        $user = $request->user();
        $isOwner = $sidang->penguji_id === $user->id;
        $isAllowedAdmin = $user->isAdmin()
            && ($user->isSystemAdmin() || ! $user->institution_id || $sidang->institution_id === $user->institution_id);
        abort_unless($isOwner || $isAllowedAdmin, 403);

        $sidang->delete();

        return back()->with('success', 'Riwayat sidang dihapus.');
    }

    private function createGradeTargets(Sidang $sidang, ?MahasiswaTa $ta, ?User $penguji): void
    {
        $targets = [];
        if ($ta) {
            foreach ([$ta->pembimbing1, $ta->pembimbing2] as $d) {
                if ($d) {
                    $targets[$d->id] = 'pembimbing';
                }
            }
        }
        if ($penguji) {
            $targets[$penguji->id] = 'penguji';
        }

        foreach ($targets as $userId => $role) {
            SidangGrade::firstOrCreate(
                ['sidang_id' => $sidang->id, 'user_id' => $userId],
                ['role' => $role]
            );
        }
    }
}
