<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\MahasiswaTa;
use App\Models\SeminarSubmission;
use App\Models\Sidang;
use App\Models\WorkspaceFile;
use App\Notifications\SeminarSubmissionNotification;
use App\Services\StorageUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SeminarSubmissionController extends Controller
{
    /**
     * Mapping fase aktif -> jenis seminar.
     */
    private function jenisFromFase(MahasiswaTa $ta): string
    {
        return match ($ta->fase) {
            'proposal' => SeminarSubmission::JENIS_PROPOSAL,
            'seminar_hasil' => SeminarSubmission::JENIS_SEMINAR_HASIL,
            'sidang' => SeminarSubmission::JENIS_SIDANG,
            'seminar_kp' => SeminarSubmission::JENIS_SEMINAR_KP,
            default => $ta->isKp() ? SeminarSubmission::JENIS_SEMINAR_KP : SeminarSubmission::JENIS_PROPOSAL,
        };
    }

    /**
     * Form isi pemberian bahan seminar/sidang.
     */
    public function create(Request $request, MahasiswaTa $mahasiswaTa): View
    {
        abort_unless($mahasiswaTa->isMember($request->user()), 403);
        $this->authorize('viewWorkspace', $mahasiswaTa);

        $jenis = $this->jenisFromFase($mahasiswaTa);
        $jenisLabel = (new SeminarSubmission(['jenis' => $jenis]))->jenisLabel();
        $institution = Institution::active();
        $defaultCatatan = $institution->seminar_hardcopy_note;
        $maxMb = $institution->maxUploadSizeMb();
        $allowedTypes = $institution->allowedFileTypes();
        $fileAccept = $institution->fileAccept();

        // Daftar dosen untuk pilihan "undangan sebagai".
        $undanganOptions = $this->undanganOptions($mahasiswaTa);

        // File workspace untuk pilihan materi.
        $workspaceFiles = $mahasiswaTa->workspaceFiles()->orderByDesc('created_at')->get();

        return view('seminar-submission.create', compact(
            'mahasiswaTa', 'jenis', 'jenisLabel', 'defaultCatatan',
            'undanganOptions', 'workspaceFiles', 'maxMb', 'allowedTypes', 'fileAccept'
        ));
    }

    /**
     * Simpan submission.
     */
    public function store(Request $request, MahasiswaTa $mahasiswaTa): RedirectResponse
    {
        abort_unless($mahasiswaTa->isMember($request->user()), 403);
        $this->authorize('viewWorkspace', $mahasiswaTa);

        $institution = Institution::active();
        $maxMb = $institution->maxUploadSizeMb();
        $allowedTypes = $institution->allowedFileTypes();
        $mimes = implode(',', array_map(fn ($t) => $t === 'pdf' ? 'pdf' : $t, $allowedTypes));

        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'waktu' => ['required', 'date_format:H:i'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'undangan' => ['required', 'file', 'mimes:'.$mimes, 'max:'.($maxMb * 1024)],
            'undangan_sebagai' => ['required', 'in:pembimbing_1,pembimbing_2,penguji_1,penguji_2'],
            'materi_upload' => ['nullable', 'file', 'mimes:'.$mimes, 'max:'.($maxMb * 1024)],
            'materi_workspace_id' => ['nullable', 'integer', 'exists:workspace_files,id'],
            'catatan_keterangan' => ['nullable', 'string'],
        ]);

        // Materi wajib: salah satu dari upload baru ATAU dari workspace.
        if ($request->file('materi_upload') === null && !$request->filled('materi_workspace_id')) {
            return back()->withErrors(['materi_upload' => 'Pilih salah satu: upload file materi atau ambil dari workspace.'])->withInput();
        }

        $jenis = $this->jenisFromFase($mahasiswaTa);
        $defaultCatatan = $institution->seminar_hardcopy_note;

        // Cek kuota dosen pembimbing sebelum menyimpan undangan + materi baru.
        $dosen = $mahasiswaTa->pembimbing1 ?: $mahasiswaTa->pembimbing2;
        if ($dosen) {
            $incoming = $request->file('undangan')->getSize()
                + ($request->file('materi_upload') ? $request->file('materi_upload')->getSize() : 0);
            app(StorageUsageService::class)->assertCanUpload($dosen, $incoming);
        }

        // Materi: upload baru ATAU dari workspace (salah satu, tidak boleh keduanya kosong).
        $materiPath = null;
        $materiOriginal = null;
        $materiWorkspaceId = null;

        if ($request->filled('materi_workspace_id') && $request->file('materi_upload') === null) {
            $file = WorkspaceFile::find($request->input('materi_workspace_id'));
            if ($file && $file->mahasiswa_ta_id === $mahasiswaTa->id) {
                $materiPath = $file->path;
                $materiOriginal = $file->original_name;
                $materiWorkspaceId = $file->id;
            }
        } elseif ($request->file('materi_upload')) {
            $materiPath = $request->file('materi_upload')->store('seminar-materials/'.$mahasiswaTa->id, 'local');
            $materiOriginal = $request->file('materi_upload')->getClientOriginalName();
        }

        $undanganPath = $request->file('undangan')->store('seminar-materials/'.$mahasiswaTa->id, 'local');

        $submission = SeminarSubmission::create([
            'mahasiswa_ta_id' => $mahasiswaTa->id,
            'jenis' => $jenis,
            'tanggal' => $data['tanggal'],
            'waktu' => $data['waktu'],
            'lokasi' => $data['lokasi'] ?? null,
            'undangan_path' => $undanganPath,
            'undangan_original_name' => $request->file('undangan')->getClientOriginalName(),
            'undangan_sebagai' => $data['undangan_sebagai'],
            'materi_path' => $materiPath,
            'materi_original_name' => $materiOriginal,
            'materi_workspace_file_id' => $materiWorkspaceId,
            'catatan_hardcopy' => $defaultCatatan,
            'catatan_keterangan' => $data['catatan_keterangan'] ?? null,
            'status' => SeminarSubmission::STATUS_SUBMITTED,
        ]);

        // Notifikasi ke dosen terkait.
        $this->notifyDosen($mahasiswaTa, $submission);

        return redirect()->route('seminar-submission.show', $submission)
            ->with('success', 'Bahan seminar/sidang berhasil dikirim.');
    }

    /**
     * Detail submission.
     */
    public function show(Request $request, SeminarSubmission $submission): View
    {
        $this->authorizeView($request->user(), $submission);

        $submission->load(['mahasiswaTa.mahasiswa', 'mahasiswaTa.pembimbing1', 'mahasiswaTa.pembimbing2', 'mahasiswaTa.penguji1', 'mahasiswaTa.penguji2', 'workspaceFile', 'sidang']);

        $isDosen = $request->user()->isDosen();
        $isMember = $submission->mahasiswaTa->isMember($request->user());

        return view('seminar-submission.show', compact('submission', 'isDosen', 'isMember'));
    }

    /**
     * Form edit (mahasiswa).
     */
    public function edit(Request $request, SeminarSubmission $submission): View
    {
        abort_unless($submission->mahasiswaTa->isMember($request->user()), 403);
        // Submission yang sudah dikonversi ke riwayat sidang tidak bisa diedit lagi.
        abort_unless($submission->sidang_id === null, 403, 'Submission sudah dikonversi ke riwayat sidang dan tidak dapat diubah.');

        $institution = Institution::active();
        $maxMb = $institution->maxUploadSizeMb();
        $allowedTypes = $institution->allowedFileTypes();
        $fileAccept = $institution->fileAccept();

        $undanganOptions = $this->undanganOptions($submission->mahasiswaTa);
        $workspaceFiles = $submission->mahasiswaTa->workspaceFiles()->orderByDesc('created_at')->get();

        return view('seminar-submission.edit', compact('submission', 'undanganOptions', 'workspaceFiles', 'maxMb', 'allowedTypes', 'fileAccept'));
    }

    /**
     * Update submission (mahasiswa).
     */
    public function update(Request $request, SeminarSubmission $submission): RedirectResponse
    {
        abort_unless($submission->mahasiswaTa->isMember($request->user()), 403);
        // Submission yang sudah dikonversi ke riwayat sidang tidak bisa diubah.
        abort_unless($submission->sidang_id === null, 403, 'Submission sudah dikonversi ke riwayat sidang dan tidak dapat diubah.');

        $institution = Institution::active();
        $maxMb = $institution->maxUploadSizeMb();
        $allowedTypes = $institution->allowedFileTypes();
        $mimes = implode(',', array_map(fn ($t) => $t === 'pdf' ? 'pdf' : $t, $allowedTypes));

        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'waktu' => ['required', 'date_format:H:i'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'undangan' => ['nullable', 'file', 'mimes:'.$mimes, 'max:'.($maxMb * 1024)],
            'undangan_sebagai' => ['required', 'in:pembimbing_1,pembimbing_2,penguji_1,penguji_2'],
            'materi_upload' => ['nullable', 'file', 'mimes:'.$mimes, 'max:'.($maxMb * 1024)],
            'materi_workspace_id' => ['nullable', 'integer', 'exists:workspace_files,id'],
            'catatan_keterangan' => ['nullable', 'string'],
        ]);

        $payload = [
            'tanggal' => $data['tanggal'],
            'waktu' => $data['waktu'],
            'lokasi' => $data['lokasi'] ?? null,
            'undangan_sebagai' => $data['undangan_sebagai'],
            'catatan_keterangan' => $data['catatan_keterangan'] ?? null,
        ];

        // Cek kuota dosen pembimbing sebelum mengganti file (undangan/materi).
        $dosen = $submission->mahasiswaTa->pembimbing1 ?: $submission->mahasiswaTa->pembimbing2;
        if ($dosen) {
            $incoming = ($request->file('undangan') ? $request->file('undangan')->getSize() : 0)
                + ($request->file('materi_upload') ? $request->file('materi_upload')->getSize() : 0);
            if ($incoming > 0) {
                app(StorageUsageService::class)->assertCanUpload($dosen, $incoming);
            }
        }

        // Ganti undangan bila ada file baru.
        if ($request->file('undangan')) {
            Storage::disk('local')->delete($submission->undangan_path);
            $payload['undangan_path'] = $request->file('undangan')->store('seminar-materials/'.$submission->mahasiswa_ta_id, 'local');
            $payload['undangan_original_name'] = $request->file('undangan')->getClientOriginalName();
        }

        // Ganti materi bila ada pilihan baru.
        if ($request->filled('materi_workspace_id') && $request->file('materi_upload') === null) {
            $file = WorkspaceFile::find($request->input('materi_workspace_id'));
            if ($file && $file->mahasiswa_ta_id === $submission->mahasiswa_ta_id) {
                $this->deleteMateriFile($submission);
                $payload['materi_path'] = $file->path;
                $payload['materi_original_name'] = $file->original_name;
                $payload['materi_workspace_file_id'] = $file->id;
            }
        } elseif ($request->file('materi_upload')) {
            $this->deleteMateriFile($submission);
            $payload['materi_path'] = $request->file('materi_upload')->store('seminar-materials/'.$submission->mahasiswa_ta_id, 'local');
            $payload['materi_original_name'] = $request->file('materi_upload')->getClientOriginalName();
            $payload['materi_workspace_file_id'] = null;
        }

        $submission->update($payload);

        return redirect()->route('seminar-submission.show', $submission)
            ->with('success', 'Bahan seminar/sidang berhasil diperbarui.');
    }

    /**
     * Dosen mengedit catatan hardcopy.
     */
    public function updateHardcopyNote(Request $request, SeminarSubmission $submission): RedirectResponse
    {
        $this->authorizeDosen($request->user(), $submission);

        $validated = $request->validate([
            'catatan_hardcopy' => ['required', 'string'],
        ]);

        $submission->update(['catatan_hardcopy' => $validated['catatan_hardcopy']]);

        return back()->with('success', 'Catatan hardcopy diperbarui.');
    }

    /**
     * Download surat undangan.
     */
    public function downloadUndangan(Request $request, SeminarSubmission $submission)
    {
        $this->authorizeView($request->user(), $submission);

        return Storage::disk('local')->download($submission->undangan_path, $submission->undangan_original_name);
    }

    /**
     * Download materi.
     */
    public function downloadMateri(Request $request, SeminarSubmission $submission)
    {
        $this->authorizeView($request->user(), $submission);

        return Storage::disk('local')->download($submission->materi_path, $submission->materi_original_name);
    }

    /**
     * Konversi ke riwayat sidang (dosen memilih penguji + hasil).
     */
    public function convertToSidang(Request $request, SeminarSubmission $submission): RedirectResponse
    {
        $this->authorizeDosen($request->user(), $submission);

        $validated = $request->validate([
            'penguji_id' => ['required', 'integer', 'exists:users,id'],
            'hasil' => ['required', 'in:'.implode(',', Sidang::HASILS)],
        ]);

        $ta = $submission->mahasiswaTa;

        // Supervisor names (pembimbing) untuk riwayat.
        $supervisorNames = array_values(array_filter([
            $ta->pembimbing1?->name,
            $ta->pembimbing2?->name,
        ]));

        $sidang = Sidang::create([
            'institution_id' => $ta->institution_id,
            'mahasiswa_ta_id' => $ta->id,
            'mahasiswa_name' => $ta->mahasiswa?->name,
            'penguji_id' => $validated['penguji_id'],
            'jenis' => $submission->jenis === SeminarSubmission::JENIS_SIDANG ? Sidang::JENIS_SIDANG : Sidang::JENIS_PROPOSAL,
            'tanggal' => $submission->tanggal,
            'hasil' => $validated['hasil'],
            'supervisor_names' => $supervisorNames ?: null,
        ]);

        $submission->update(['sidang_id' => $sidang->id]);

        return back()->with('success', 'Submission dikonversi ke riwayat sidang.');
    }

    /**
     * Daftar pilihan "undangan sebagai" dari data mahasiswa.
     */
    private function undanganOptions(MahasiswaTa $ta): array
    {
        $options = [];
        if ($ta->pembimbing1) {
            $options['pembimbing_1'] = 'Pembimbing 1 — '.$ta->pembimbing1->name;
        }
        if ($ta->pembimbing2) {
            $options['pembimbing_2'] = 'Pembimbing 2 — '.$ta->pembimbing2->name;
        }
        if ($ta->penguji1) {
            $options['penguji_1'] = 'Penguji 1 — '.$ta->penguji1->name;
        }
        if ($ta->penguji2) {
            $options['penguji_2'] = 'Penguji 2 — '.$ta->penguji2->name;
        }

        return $options;
    }

    /**
     * Notifikasi ke dosen terkait (pembimbing + penguji).
     */
    private function notifyDosen(MahasiswaTa $ta, SeminarSubmission $submission): void
    {
        foreach ($ta->allDosenIds() as $dosenId) {
            if ($dosen = \App\Models\User::find($dosenId)) {
                $this->bestEffort(fn () => $dosen->notify(new SeminarSubmissionNotification($submission)));
            }
        }
    }

    /**
     * Hapus file materi (jika bukan dari workspace).
     */
    private function deleteMateriFile(SeminarSubmission $submission): void
    {
        if (!$submission->materiFromWorkspace() && $submission->materi_path) {
            Storage::disk('local')->delete($submission->materi_path);
        }
    }

    /**
     * Otorisasi akses view: member, dosen terkait, atau admin.
     */
    private function authorizeView($user, SeminarSubmission $submission): void
    {
        $ta = $submission->mahasiswaTa;

        if ($user->isAdmin()) {
            return;
        }

        if ($ta->isMember($user)) {
            return;
        }

        if ($user->isDosen() && ($ta->isPembimbing($user) || $ta->isPenguji($user))) {
            return;
        }

        abort(403);
    }

    /**
     * Otorisasi dosen terkait (pembimbing/penguji) atau admin.
     */
    private function authorizeDosen($user, SeminarSubmission $submission): void
    {
        $ta = $submission->mahasiswaTa;

        if ($user->isAdmin()) {
            return;
        }

        abort_unless($user->isDosen() && ($ta->isPembimbing($user) || $ta->isPenguji($user)), 403);
    }

}
