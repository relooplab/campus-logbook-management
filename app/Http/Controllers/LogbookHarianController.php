<?php

namespace App\Http\Controllers;

use App\Models\LogbookHarianKp;
use App\Models\MahasiswaTa;
use App\Notifications\ActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Logbook harian KP: catatan kegiatan lapangan singkat mahasiswa selama
 * periode KP. Tidak ada alur review/approval dosen, tetapi dosen pembimbing
 * mendapat notifikasi setiap kali mahasiswa menambah catatan.
 */
class LogbookHarianController extends Controller
{
    /**
     * Daftar catatan harian milik program KP tertentu.
     * Mahasiswa pemilik & dosen pembimbing dapat melihat.
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

        $entries = $mahasiswaTa->logbookHarian()
            ->orderByDesc('tanggal')
            ->paginate(20)
            ->withQueryString();

        return view('logbook-harian.index', compact('mahasiswaTa', 'entries'));
    }

    /**
     * Form tambah catatan harian (khusus mahasiswa pemilik KP).
     */
    public function create(Request $request, MahasiswaTa $mahasiswaTa): View
    {
        abort_unless($mahasiswaTa->isKp(), 404, 'Program bukan KP.');
        abort_unless($mahasiswaTa->isMember($request->user()), 403, 'Hanya anggota kelompok KP yang dapat menambah catatan.');

        return view('logbook-harian.create', compact('mahasiswaTa'));
    }

    /**
     * Simpan catatan harian baru + notifikasi ke dosen pembimbing.
     */
    public function store(Request $request, MahasiswaTa $mahasiswaTa): RedirectResponse
    {
        abort_unless($mahasiswaTa->isKp(), 404, 'Program bukan KP.');
        abort_unless($mahasiswaTa->isMember($request->user()), 403, 'Hanya anggota kelompok KP yang dapat menambah catatan.');

        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'kegiatan' => ['required', 'string', 'max:5000'],
            'kendala' => ['nullable', 'string', 'max:5000'],
            'foto_1' => ['nullable', 'file', 'image', 'max:5120'],
            'foto_2' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        $entry = $mahasiswaTa->logbookHarian()->create([
            'tanggal' => $validated['tanggal'],
            'kegiatan' => $validated['kegiatan'],
            'kendala' => $validated['kendala'] ?? null,
        ]);

        // Upload foto (maks 2) ke disk public.
        if ($request->hasFile('foto_1')) {
            $entry->update(['foto_1' => $this->storeFoto($request->file('foto_1'), $entry->id, 1)]);
        }
        if ($request->hasFile('foto_2')) {
            $entry->update(['foto_2' => $this->storeFoto($request->file('foto_2'), $entry->id, 2)]);
        }

        // Notifikasi ke dosen pembimbing (P1 & P2).
        $this->notifyDosen($mahasiswaTa, $entry);

        return redirect()->route('logbook-harian.index', $mahasiswaTa)
            ->with('success', 'Catatan harian berhasil ditambahkan.');
    }

    /**
     * Form edit catatan harian (khusus mahasiswa pemilik KP).
     */
    public function edit(Request $request, MahasiswaTa $mahasiswaTa, LogbookHarianKp $logbookHarian): View
    {
        abort_unless($mahasiswaTa->isKp(), 404, 'Program bukan KP.');
        abort_unless($mahasiswaTa->isMember($request->user()), 403, 'Hanya anggota kelompok KP yang dapat mengubah catatan.');
        abort_unless($logbookHarian->mahasiswa_ta_id === $mahasiswaTa->id, 404);

        return view('logbook-harian.edit', compact('mahasiswaTa', 'logbookHarian'));
    }

    /**
     * Perbarui catatan harian.
     */
    public function update(Request $request, MahasiswaTa $mahasiswaTa, LogbookHarianKp $logbookHarian): RedirectResponse
    {
        abort_unless($mahasiswaTa->isKp(), 404, 'Program bukan KP.');
        abort_unless($mahasiswaTa->isMember($request->user()), 403, 'Hanya anggota kelompok KP yang dapat mengubah catatan.');
        abort_unless($logbookHarian->mahasiswa_ta_id === $mahasiswaTa->id, 404);

        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'kegiatan' => ['required', 'string', 'max:5000'],
            'kendala' => ['nullable', 'string', 'max:5000'],
            'foto_1' => ['nullable', 'file', 'image', 'max:5120'],
            'foto_2' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        $logbookHarian->update([
            'tanggal' => $validated['tanggal'],
            'kegiatan' => $validated['kegiatan'],
            'kendala' => $validated['kendala'] ?? null,
        ]);

        // Ganti foto jika ada file baru; hapus foto lama dari storage.
        if ($request->hasFile('foto_1')) {
            $this->deleteFoto($logbookHarian->foto_1);
            $logbookHarian->update(['foto_1' => $this->storeFoto($request->file('foto_1'), $logbookHarian->id, 1)]);
        }
        if ($request->hasFile('foto_2')) {
            $this->deleteFoto($logbookHarian->foto_2);
            $logbookHarian->update(['foto_2' => $this->storeFoto($request->file('foto_2'), $logbookHarian->id, 2)]);
        }

        return redirect()->route('logbook-harian.index', $mahasiswaTa)
            ->with('success', 'Catatan harian berhasil diperbarui.');
    }

    /**
     * Hapus catatan harian (khusus mahasiswa pemilik KP).
     */
    public function destroy(Request $request, MahasiswaTa $mahasiswaTa, LogbookHarianKp $logbookHarian): RedirectResponse
    {
        abort_unless($mahasiswaTa->isKp(), 404, 'Program bukan KP.');
        abort_unless($mahasiswaTa->isMember($request->user()), 403, 'Hanya anggota kelompok KP yang dapat menghapus catatan.');
        abort_unless($logbookHarian->mahasiswa_ta_id === $mahasiswaTa->id, 404);

        $this->deleteFoto($logbookHarian->foto_1);
        $this->deleteFoto($logbookHarian->foto_2);
        $logbookHarian->delete();

        return redirect()->route('logbook-harian.index', $mahasiswaTa)
            ->with('success', 'Catatan harian berhasil dihapus.');
    }

    /**
     * Simpan foto ke disk public: logbook-harian/{entry_id}/foto{n}.{ext}.
     */
    private function storeFoto($file, int $entryId, int $index): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $name = 'foto'.$index.'.'.$ext;

        return $file->storeAs('logbook-harian/'.$entryId, $name, 'public');
    }

    /**
     * Hapus foto dari disk public (jika ada).
     */
    private function deleteFoto(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Kirim notifikasi ke dosen pembimbing (P1 & P2) bahwa ada catatan harian baru.
     */
    private function notifyDosen(MahasiswaTa $mahasiswaTa, LogbookHarianKp $entry): void
    {
        $url = route('logbook-harian.index', $mahasiswaTa);
        $message = 'Mahasiswa '.($mahasiswaTa->mahasiswa?->name ?? '').' menambahkan logbook harian KP ('.$entry->tanggal->format('d M Y').').';

        foreach ([$mahasiswaTa->pembimbing1, $mahasiswaTa->pembimbing2] as $dosen) {
            if ($dosen) {
                try {
                    $dosen->notify(new ActivityNotification($message, $url, 'Logbook Harian KP Baru'));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
    }
}