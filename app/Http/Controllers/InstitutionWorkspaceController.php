<?php

namespace App\Http\Controllers;

use App\Models\InstitutionWorkspace;
use App\Models\InstitutionWorkspaceFile;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class InstitutionWorkspaceController extends Controller
{
    /**
     * Dashboard workspace institusi — grouping per level, hanya yang bisa diakses.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Semua workspace yang bisa diakses user.
        $workspaces = InstitutionWorkspace::with('files', 'allowedUsers', 'creator')
            ->get()
            ->filter(fn ($ws) => $ws->isAccessibleBy($user))
            ->values();

        // Grouping per level.
        $grouped = $workspaces->groupBy('scope_type');

        return view('workspace-institusi.index', compact('grouped', 'user'));
    }

    /**
     * Detail workspace + daftar file.
     */
    public function show(Request $request, InstitutionWorkspace $workspace): View
    {
        $user = $request->user();
        abort_unless($workspace->isAccessibleBy($user), 403, 'Anda tidak memiliki akses ke workspace ini.');

        $workspace->load(['files.uploader', 'files.deletedBy', 'allowedUsers', 'creator']);

        return view('workspace-institusi.show', compact('workspace', 'user'));
    }

    /**
     * Buat workspace baru (admin, terikat langganan).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403, 'Hanya admin yang dapat membuat workspace institusi.');

        $validated = $request->validate([
            'scope_type' => ['required', 'in:university,faculty,department,study_program'],
            'scope_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'access_mode' => ['required', 'in:hierarchical,custom'],
        ]);

        // Gate langganan: node (atau leluhurnya) harus ter-cover langganan aktif.
        if (!Feature::directorySubscriptionActive($validated['scope_type'], (int) $validated['scope_id'])) {
            return back()->with('error', 'Node ini belum ter-cover langganan aktif. Aktifkan langganan dulu.');
        }

        // Admin hanya bisa buat workspace di simpul yang dia kelola (admin_scope).
        $canCreate = \App\Models\AdminScope::where('user_id', $user->id)
            ->where('status', \App\Models\AdminScope::STATUS_ACTIVE)
            ->where('scope_type', $validated['scope_type'])
            ->where('scope_id', (int) $validated['scope_id'])
            ->exists();

        // system_admin boleh buat di mana saja.
        if (!$canCreate && !$user->isSystemAdmin()) {
            return back()->with('error', 'Anda hanya dapat membuat workspace di simpul yang Anda kelola.');
        }

        $workspace = InstitutionWorkspace::create([
            'institution_id' => $user->institution_id,
            'scope_type' => $validated['scope_type'],
            'scope_id' => (int) $validated['scope_id'],
            'name' => $validated['name'],
            'access_mode' => $validated['access_mode'],
            'created_by' => $user->id,
        ]);

        return redirect()->route('workspace-institusi.show', $workspace)
            ->with('success', 'Workspace institusi berhasil dibuat.');
    }

    /**
     * Upload file — admin di simpul yang sama.
     */
    public function upload(Request $request, InstitutionWorkspace $workspace): RedirectResponse
    {
        $user = $request->user();
        abort_unless($workspace->canManage($user), 403, 'Anda tidak memiliki kewenangan untuk mengunggah file.');

        $validated = $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'max:51200'], // 50 MB
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($request->file('files') as $file) {
            $stored = $file->store('workspace-institusi/'.$workspace->id, 'local');

            InstitutionWorkspaceFile::create([
                'institution_workspace_id' => $workspace->id,
                'uploaded_by' => $user->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $stored,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'description' => $validated['description'] ?? null,
            ]);
        }

        return back()->with('success', 'File berhasil diunggah ke workspace.');
    }

    /**
     * Hapus file (soft delete + fingerprint deleted_by) — admin di simpul yang sama.
     */
    public function destroyFile(Request $request, InstitutionWorkspace $workspace, InstitutionWorkspaceFile $file): RedirectResponse
    {
        $user = $request->user();
        abort_unless($workspace->canManage($user), 403, 'Anda tidak memiliki kewenangan untuk menghapus file.');

        // Pastikan file milik workspace ini.
        abort_unless($file->institution_workspace_id === $workspace->id, 404);

        Storage::disk('local')->delete($file->path);
        $file->update([
            'deleted_by' => $user->id,
            'deleted_at' => now(),
        ]);

        return back()->with('success', 'File dihapus.');
    }

    /**
     * Download file.
     */
    public function download(Request $request, InstitutionWorkspace $workspace, InstitutionWorkspaceFile $file)
    {
        $user = $request->user();
        abort_unless($workspace->isAccessibleBy($user), 403, 'Anda tidak memiliki akses ke workspace ini.');
        abort_unless($file->institution_workspace_id === $workspace->id, 404);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    /**
     * Preview PDF inline (atau redirect ke download utk non-PDF).
     */
    public function preview(Request $request, InstitutionWorkspace $workspace, InstitutionWorkspaceFile $file)
    {
        $user = $request->user();
        abort_unless($workspace->isAccessibleBy($user), 403, 'Anda tidak memiliki akses ke workspace ini.');
        abort_unless($file->institution_workspace_id === $workspace->id, 404);

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
     * Atur akses custom (grant/revoke dosen) — admin di simpul yang sama.
     */
    public function updateAccess(Request $request, InstitutionWorkspace $workspace): RedirectResponse
    {
        $user = $request->user();
        abort_unless($workspace->canManage($user), 403, 'Anda tidak memiliki kewenangan untuk mengatur akses.');

        $validated = $request->validate([
            'access_mode' => ['required', 'in:hierarchical,custom'],
            'allowed_user_ids' => ['nullable', 'array'],
            'allowed_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $workspace->update(['access_mode' => $validated['access_mode']]);

        // Sync allowed users.
        $workspace->allowedUsers()->sync($validated['allowed_user_ids'] ?? []);

        return back()->with('success', 'Pengaturan akses workspace diperbarui.');
    }
}