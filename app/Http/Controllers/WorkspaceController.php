<?php

namespace App\Http\Controllers;

use App\Events\WorkspaceFileUploaded;
use App\Http\Requests\StoreWorkspaceFileRequest;
use App\Models\MahasiswaTa;
use App\Models\User;
use App\Models\WorkspaceFile;
use App\Support\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    /**
     * Daftar file workspace.
     */
    public function index(Request $request, MahasiswaTa $mahasiswaTa): View
    {
        $this->authorize('viewWorkspace', $mahasiswaTa);

        $query = $mahasiswaTa->workspaceFiles()->with('uploader');

        $query->when($request->filled('bab'), fn ($q) => $q->where('bab', $request->query('bab')))
            ->when($request->filled('type'), function ($q) use ($request) {
                $type = $request->query('type');
                $q->where(function ($qq) use ($type) {
                    $qq->where('mime_type', 'like', $type === 'pdf' ? '%pdf%'
                        : ($type === 'doc' ? '%word%'
                        : ($type === 'xls' ? '%excel%' : '%'.$type.'%')));
                });
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->query('search');
                $q->where(fn ($qq) => $qq->where('original_name', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%"));
            });

        $files = $query->orderByDesc('created_at')->get();

        // Group by bab (null -> "Lainnya").
        $grouped = $files->groupBy(fn ($f) => $f->bab ?: 'Lainnya');

        // Statistik penyimpanan.
        $totalBytes = $files->sum('size');

        return view('workspace.index', compact('mahasiswaTa', 'grouped', 'totalBytes'));
    }

    /**
     * Upload multi-file — hanya mahasiswa pemilik TA.
     */
    public function store(StoreWorkspaceFileRequest $request, MahasiswaTa $mahasiswaTa): RedirectResponse
    {
        $this->authorize('viewWorkspace', $mahasiswaTa);
        abort_unless($mahasiswaTa->isMember($request->user()), 403, 'Hanya anggota kelompok yang dapat menambah file.');

        $bab = $request->input('bab');

        foreach ($request->file('files') as $file) {
            $stored = $file->store('workspace/'.$mahasiswaTa->id, 'local');

            WorkspaceFile::create([
                'mahasiswa_ta_id' => $mahasiswaTa->id,
                'uploaded_by' => $request->user()->id,
                'bab' => $bab,
                'original_name' => $file->getClientOriginalName(),
                'path' => $stored,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        // Notifikasi ke dosen pembimbing.
        $this->notifyCounterpart($request, $mahasiswaTa);

        return back()->with('success', 'File berhasil diunggah ke workspace.');
    }

    /**
     * Workspace pribadi dosen (user_id) — file milik dosen itu sendiri.
     */
    public function personalIndex(Request $request): View
    {
        $user = $request->user();

        $query = WorkspaceFile::where('user_id', $user->id)->with('uploader');

        $query->when($request->filled('bab'), fn ($q) => $q->where('bab', $request->query('bab')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->query('search');
                $q->where(fn ($qq) => $qq->where('original_name', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%"));
            });

        $files = $query->orderByDesc('created_at')->get();

        // Group by bab (null -> "Lainnya").
        $grouped = $files->groupBy(fn ($f) => $f->bab ?: 'Lainnya');

        $totalBytes = $files->sum('size');

        return view('workspace.personal', compact('grouped', 'totalBytes', 'user'));
    }

    /**
     * Upload file ke workspace pribadi dosen.
     */
    public function personalStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'max:5'],
            'files.*' => ['file', 'max:25600'], // 25 MB
            'bab' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $bab = $request->input('bab');

        foreach ($request->file('files') as $file) {
            $stored = $file->store('workspace/dosen/'.$user->id, 'local');

            WorkspaceFile::create([
                'user_id' => $user->id,
                'uploaded_by' => $user->id,
                'bab' => $bab,
                'original_name' => $file->getClientOriginalName(),
                'path' => $stored,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return back()->with('success', 'File berhasil diunggah ke workspace pribadi.');
    }

    /**
     * Download file (attachment dengan original_name).
     */
    public function download(Request $request, WorkspaceFile $file)
    {
        $this->authorizeFileAccess($request->user(), $file);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    /**
     * Preview PDF inline (atau redirect ke download utk non-PDF).
     */
    public function preview(Request $request, WorkspaceFile $file)
    {
        $this->authorizeFileAccess($request->user(), $file);

        if (!$file->isPdf()) {
            return Storage::disk('local')->download($file->path, $file->original_name);
        }

        $fullPath = Storage::disk('local')->path($file->path);
        $size = filesize($fullPath);

        return response()->streamDownload(function () use ($fullPath) {
            readfile($fullPath);
        }, $file->original_name, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => $size,
            'Cache-Control' => 'private, no-transform',
        ], 'inline');
    }

    /**
     * Edit metadata (bab & description) — hanya pemilik file.
     */
    public function update(Request $request, WorkspaceFile $file): RedirectResponse
    {
        $this->authorizeFileAccess($request->user(), $file);
        abort_unless($this->canModify($request->user(), $file), 403, 'Anda tidak berhak mengedit file ini.');

        $validated = $request->validate([
            'bab' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $file->update($validated);

        return back()->with('success', 'Metadata file diperbarui.');
    }

    /**
     * Hapus file — hanya pemilik file.
     */
    public function destroy(Request $request, WorkspaceFile $file): RedirectResponse
    {
        $this->authorizeFileAccess($request->user(), $file);
        abort_unless($this->canModify($request->user(), $file), 403, 'Anda tidak berhak menghapus file ini.');

        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('success', 'File dihapus.');
    }

    /**
     * Otorisasi akses file: file dosen (user_id) hanya pemiliknya;
     * file mahasiswa (mahasiswa_ta_id) mengikuti policy workspace TA.
     */
    private function authorizeFileAccess(User $user, WorkspaceFile $file): void
    {
        if ($file->user_id) {
            abort_unless($file->user_id === $user->id, 403, 'Anda tidak berhak mengakses file ini.');
            return;
        }

        $this->authorize('viewWorkspace', $file->mahasiswaTa);
    }

    /**
     * Apakah user boleh memodifikasi (edit/hapus) file ini.
     */
    private function canModify(User $user, WorkspaceFile $file): bool
    {
        if ($file->user_id) {
            return $file->user_id === $user->id;
        }

        return $file->mahasiswaTa?->isMember($user) ?? false;
    }

    /**
     * Notifikasi ke pihak lawan (dosen upload -> mahasiswa, mahasiswa upload -> dosen).
     */
    private function notifyCounterpart(Request $request, MahasiswaTa $mahasiswaTa): void
    {
        $uploader = $request->user();

        // Mahasiswa upload -> beri tahu dosen pembimbing.
        foreach ([$mahasiswaTa->pembimbing1, $mahasiswaTa->pembimbing2] as $dosen) {
            if ($dosen && $dosen->id !== $uploader->id) {
                $this->bestEffort(fn () => $dosen->notify(new \App\Notifications\ActivityNotification(
                    'Mahasiswa mengunggah file baru ke workspace.',
                    route('workspace.index', $mahasiswaTa),
                    'File Baru di Workspace',
                )));
            }
        }
    }
}
