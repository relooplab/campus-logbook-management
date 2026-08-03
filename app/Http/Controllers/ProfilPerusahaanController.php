<?php

namespace App\Http\Controllers;

use App\Models\MahasiswaTa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Profil Perusahaan KP: profil singkat perusahaan tempat KP yang diisi
 * oleh mahasiswa, termasuk pembimbing lapangan.
 */
class ProfilPerusahaanController extends Controller
{
    /** Daftar jenis instansi/tempat KP. */
    public const JENIS_INSTANSI = [
        'instansi_pemerintah' => 'Instansi pemerintah',
        'bumn_bumd' => 'BUMN/BUMD',
        'perusahaan_swasta' => 'Perusahaan swasta',
        'konsultan' => 'Konsultan',
        'lembaga_riset_pendidikan' => 'Lembaga riset & Pendidikan',
        'ngo_komunitas' => 'NGO/komunitas',
        'media_kreatif' => 'Media & kreatif',
    ];

    /**
     * Tampilkan & edit profil perusahaan (khusus program KP).
     */
    public function index(Request $request, MahasiswaTa $mahasiswaTa): View
    {
        abort_unless($mahasiswaTa->isKp(), 404, 'Program bukan KP.');

        $user = $request->user();
        if ($user->isMahasiswa() && !$mahasiswaTa->isMember($user)) {
            abort(403);
        }
        if ($user->isDosen() && !$mahasiswaTa->isPembimbing($user)) {
            abort(403, 'Anda bukan pembimbing KP ini.');
        }

        $jenisInstansi = self::JENIS_INSTANSI;

        return view('profil-perusahaan.index', compact('mahasiswaTa', 'jenisInstansi'));
    }

    /**
     * Simpan profil perusahaan (khusus anggota kelompok KP).
     */
    public function update(Request $request, MahasiswaTa $mahasiswaTa): RedirectResponse
    {
        abort_unless($mahasiswaTa->isKp(), 404, 'Program bukan KP.');
        abort_unless($mahasiswaTa->isMember($request->user()), 403, 'Hanya anggota kelompok KP yang dapat mengubah profil perusahaan.');

        $validated = $request->validate([
            'tempat_kp' => ['required', 'string', 'max:255'],
            'alamat_perusahaan' => ['nullable', 'string', 'max:500'],
            'jenis_instansi' => ['nullable', 'in:'.implode(',', array_keys(self::JENIS_INSTANSI))],
            'pembimbing_lapangan' => ['nullable', 'string', 'max:255'],
            'profil_perusahaan' => ['nullable', 'string', 'max:5000'],
        ]);

        $mahasiswaTa->update($validated);

        return back()->with('success', 'Profil perusahaan berhasil diperbarui.');
    }
}