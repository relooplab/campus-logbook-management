<?php

namespace App\Http\Controllers;

use App\Imports\MahasiswaImport;
use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\User;
use App\Models\WorkspaceFile;
use App\Support\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class UtilityController extends Controller
{
    /**
     * Global search (Cmd+K): mahasiswa, entry, file workspace.
     */
    public function globalSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        $user = $request->user();

        if ($q === '') {
            return response()->json(['users' => [], 'entries' => [], 'files' => []]);
        }

        // Mahasiswa & dosen.
        $users = User::where(function ($w) use ($q) {
            $w->where('name', 'like', "%{$q}%")
                ->orWhere('identifier', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%");
        })->limit(8)->get(['id', 'name', 'identifier', 'roles']);

        // Entry logbook (topik / progres / mahasiswa).
        $entries = LogbookEntry::where(function ($w) use ($q) {
            $w->where('topik', 'like', "%{$q}%")
                ->orWhere('progres_kendala', 'like', "%{$q}%")
                ->orWhereHas('mahasiswaTa.mahasiswa', fn ($m) => $m->where('name', 'like', "%{$q}%"));
        })->with('mahasiswaTa.mahasiswa')->limit(8)->get();

        // File workspace.
        $files = WorkspaceFile::where(function ($w) use ($q) {
            $w->where('original_name', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%");
        })->with('mahasiswaTa.mahasiswa')->limit(8)->get();

        return response()->json([
            'users' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'identifier' => $u->identifier,
                'url' => route('profile.show', $u),
            ]),
            'entries' => $entries->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->topik ?: ('Revisi '.$e->mahasiswaTa?->mahasiswa?->name),
                'student' => $e->mahasiswaTa?->mahasiswa?->name,
                'url' => route('logbook.show', $e),
            ]),
            'files' => $files->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->original_name,
                'student' => $f->mahasiswaTa?->mahasiswa?->name,
                'url' => $f->isPdf() ? route('workspace.preview', $f) : route('workspace.download', $f),
            ]),
        ]);
    }

    /**
     * Import mahasiswa via Excel (admin).
     */
    public function importMahasiswa(Request $request): RedirectResponse
    {
        // Bulk import = fitur prodi, hanya di mode institusi.
        abort_unless(Feature::has('bulk_import'), 403, 'Fitur import massal hanya tersedia di mode institusi.');

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'pembimbing_default' => ['nullable', 'exists:users,id'],
        ]);

        $defaultPembimbing = $request->input('pembimbing_default')
            ? (int) $request->input('pembimbing_default')
            : User::role('dosen')->first()->id;

        $targetSesi = (int) $request->input('target_sesi', 7);

        Excel::import(new MahasiswaImport($defaultPembimbing, $targetSesi), $request->file('file'));

        return back()->with('success', 'Import mahasiswa selesai.');
    }
}
