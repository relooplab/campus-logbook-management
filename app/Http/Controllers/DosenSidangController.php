<?php

namespace App\Http\Controllers;

use App\Models\MahasiswaTa;
use App\Models\Sidang;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fase D: dosen (khususnya mode individual) mencatat sidang / riwayat menguji,
 * termasuk mahasiswa ORANG LAIN (di luar bimbingannya).
 */
class DosenSidangController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $sidangs = Sidang::where('penguji_id', $user->id)
            ->with('mahasiswaTa.mahasiswa')
            ->orderByDesc('tanggal')
            ->get();

        // Mahasiswa bimbingan (untuk dropdown, bila ingin mencatat bimbingannya).
        $bimbingan = MahasiswaTa::where('pembimbing_1_id', $user->id)
            ->orWhere('pembimbing_2_id', $user->id)
            ->with('mahasiswa')
            ->get();

        return view('dosen-sidang.index', compact('sidangs', 'bimbingan'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'mahasiswa_ta_id' => ['nullable', 'exists:mahasiswa_ta,id'],
            'mahasiswa_name' => ['nullable', 'string', 'max:255'],
            'jenis' => ['required', 'in:'.implode(',', Sidang::JENISES)],
            'tanggal' => ['required', 'date'],
            'hasil' => ['nullable', 'in:'.implode(',', Sidang::HASILS)],
            'supervisor_1' => ['nullable', 'string', 'max:255'],
            'supervisor_2' => ['nullable', 'string', 'max:255'],
            'supervisor_3' => ['nullable', 'string', 'max:255'],
        ]);

        // Butuh mahasiswa_ta_id ATAU mahasiswa_name.
        if (empty($validated['mahasiswa_ta_id']) && empty($validated['mahasiswa_name'])) {
            return back()->withErrors(['mahasiswa' => 'Pilih mahasiswa bimbingan atau isi nama mahasiswa.']);
        }

        $supervisors = [];
        foreach (['supervisor_1', 'supervisor_2', 'supervisor_3'] as $f) {
            $v = trim((string) ($validated[$f] ?? ''));
            if ($v !== '') {
                $supervisors[] = $v;
            }
        }

        Sidang::create([
            'institution_id' => $user->institution_id,
            'mahasiswa_ta_id' => ($validated['mahasiswa_ta_id'] ?? null) ?: null,
            'mahasiswa_name' => ($validated['mahasiswa_name'] ?? null) ?: null,
            'penguji_id' => $user->id,
            'jenis' => $validated['jenis'],
            'tanggal' => $validated['tanggal'],
            'hasil' => $validated['hasil'] ?? null,
            'supervisor_names' => $supervisors ?: null,
        ]);

        return redirect()->route('dosen-sidang.index')
            ->with('success', 'Sidang dicatat.');
    }

    public function destroy(Request $request, Sidang $sidang): RedirectResponse
    {
        abort_unless($sidang->penguji_id === $request->user()->id || $request->user()->isAdmin(), 403);

        $sidang->delete();

        return back()->with('success', 'Sidang dihapus.');
    }
}
