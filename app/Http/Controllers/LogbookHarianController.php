<?php

namespace App\Http\Controllers;

use App\Models\LogbookHarianKp;
use App\Models\MahasiswaTa;
use App\Notifications\ActivityNotification;
use App\Services\StorageUsageService;
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
     * Sajikan foto kegiatan (disk local, lewat otorisasi) — hanya anggota
     * kelompok KP & dosen pembimbing yang boleh melihat.
     */
    public function foto(Request $request, MahasiswaTa $mahasiswaTa, LogbookHarianKp $logbookHarian, int $index)
    {
        abort_unless($mahasiswaTa->isKp(), 404, 'Program bukan KP.');
        abort_unless($logbookHarian->mahasiswa_ta_id === $mahasiswaTa->id, 404);

        $user = $request->user();
        if ($user->isMahasiswa() && !$mahasiswaTa->isMember($user)) {
            abort(403);
        }
        if ($user->isDosen() && !$mahasiswaTa->isPembimbing($user)) {
            abort(403, 'Anda bukan pembimbing KP ini.');
        }

        abort_unless(in_array($index, [1, 2], true), 404);
        $path = $index === 1 ? $logbookHarian->foto_1 : $logbookHarian->foto_2;
        abort_unless($path, 404, 'Foto tidak tersedia.');
        abort_unless(Storage::disk('local')->exists($path), 404, 'Foto tidak ditemukan.');

        return Storage::disk('local')->response($path);
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

        $validated = $request->validate($this->rules($mahasiswaTa));

        $entry = $mahasiswaTa->logbookHarian()->create([
            'created_by' => $request->user()->id,
            'tanggal' => $validated['tanggal'],
            'kegiatan' => $validated['kegiatan'],
            'kendala' => $validated['kendala'] ?? null,
        ]);

        // Cek kuota dosen pembimbing sebelum upload foto.
        $dosen = $mahasiswaTa->pembimbing1 ?: $mahasiswaTa->pembimbing2;
        $storeFotos = function () use ($request, $entry) {
            if ($request->hasFile('foto_1')) {
                $entry->update(['foto_1' => $this->storeFoto($request->file('foto_1'), $entry->id, 1)]);
            }
            if ($request->hasFile('foto_2')) {
                $entry->update(['foto_2' => $this->storeFoto($request->file('foto_2'), $entry->id, 2)]);
            }
        };

        if ($dosen) {
            $incoming = collect(['foto_1', 'foto_2'])
                ->sum(fn ($f) => $request->hasFile($f) ? $request->file($f)->getSize() : 0);
            app(StorageUsageService::class)->withUploadLock($dosen, $incoming, $storeFotos);
        } else {
            $storeFotos();
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
        // Bug 3: Hanya penulis asli yang boleh mengedit catatan.
        abort_unless($logbookHarian->created_by === $request->user()->id, 403, 'Anda bukan penulis catatan ini.');

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
        // Bug 3: Hanya penulis asli yang boleh mengubah catatan.
        abort_unless($logbookHarian->created_by === $request->user()->id, 403, 'Anda bukan penulis catatan ini.');

        $validated = $request->validate($this->rules($mahasiswaTa));

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
        // Bug 3: Hanya penulis asli yang boleh menghapus catatan.
        abort_unless($logbookHarian->created_by === $request->user()->id, 403, 'Anda bukan penulis catatan ini.');

        $this->deleteFoto($logbookHarian->foto_1);
        $this->deleteFoto($logbookHarian->foto_2);
        $logbookHarian->delete();

        return redirect()->route('logbook-harian.index', $mahasiswaTa)
            ->with('success', 'Catatan harian berhasil dihapus.');
    }

    /**
     * Simpan foto ke disk local (privat, disajikan lewat foto() dengan otorisasi):
     * logbook-harian/{entry_id}/foto{n}.{ext}.
     * Ekstensi ditentukan dari deteksi konten server (getimagesize), bukan dari
     * nama asli client — mencegah file polyglot dieksekusi sebagai PHP.
     */
    private function storeFoto($file, int $entryId, int $index): string
    {
        // Verifikasi file benar-benar gambar via deteksi konten server.
        $imageInfo = @getimagesize($file->getRealPath());
        if ($imageInfo === false) {
            abort(422, 'File harus berupa gambar yang valid.');
        }

        // Peta MIME -> ekstensi aman (hanya ekstensi gambar).
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $ext = $mimeToExt[$imageInfo['mime']] ?? 'jpg';

        $name = 'foto'.$index.'.'.$ext;

        return $file->storeAs('logbook-harian/'.$entryId, $name, 'local');
    }

    /**
     * Hapus foto dari disk local (jika ada).
     */
    private function deleteFoto(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Aturan validasi logbook harian, termasuk batasan tanggal terhadap periode KP.
     * Bug 2: tanggal harus dalam rentang periode_mulai s/d periode_selesai KP.
     */
    private function rules(MahasiswaTa $mahasiswaTa): array
    {
        $rules = [
            'tanggal' => ['required', 'date'],
            'kegiatan' => ['required', 'string', 'max:5000'],
            'kendala' => ['nullable', 'string', 'max:5000'],
            'foto_1' => ['nullable', 'file', 'image', 'max:5120'],
            'foto_2' => ['nullable', 'file', 'image', 'max:5120'],
        ];

        if ($mahasiswaTa->periode_mulai) {
            $rules['tanggal'][] = 'after_or_equal:'.$mahasiswaTa->periode_mulai->format('Y-m-d');
        }
        if ($mahasiswaTa->periode_selesai) {
            $rules['tanggal'][] = 'before_or_equal:'.$mahasiswaTa->periode_selesai->format('Y-m-d');
        }

        return $rules;
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