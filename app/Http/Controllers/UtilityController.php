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

        // Mahasiswa & dosen — hanya yang punya hubungan langsung dengan pencari.
        $users = User::where(function ($w) use ($q) {
            $w->where('name', 'like', "%{$q}%")
                ->orWhere('identifier', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%");
        })
        ->get(['id', 'name', 'identifier'])
        ->filter(fn ($u) => $user->isAdmin() || $user->id === $u->id || $user->hasDirectRelation($u))
        ->take(8)
        ->values();

        // Entry logbook — hanya dari TA yang terhubung dengan pencari.
        $entries = LogbookEntry::where(function ($w) use ($q) {
            $w->where('topik', 'like', "%{$q}%")
                ->orWhere('progres_kendala', 'like', "%{$q}%")
                ->orWhereHas('mahasiswaTa.mahasiswa', fn ($m) => $m->where('name', 'like', "%{$q}%"));
        })
        ->with('mahasiswaTa.mahasiswa')
        ->get()
        ->filter(function ($e) use ($user) {
            if ($user->isAdmin()) {
                return true;
            }
            $ta = $e->mahasiswaTa;
            if (!$ta) {
                return false;
            }
            // Mahasiswa pemilik/anggota TA.
            if ($ta->isMember($user)) {
                return true;
            }
            // Dosen pembimbing/penguji.
            if ($user->isDosen() && ($ta->isPembimbing($user) || $ta->isPenguji($user))) {
                return true;
            }
            // Dosen lain yang punya hubungan langsung dengan dosen terkait TA.
            if ($user->isDosen()) {
                foreach ($ta->allDosenIds() as $dosenId) {
                    if ($dosen = User::find($dosenId)) {
                        if ($user->hasDirectRelation($dosen)) {
                            return true;
                        }
                    }
                }
            }
            return false;
        })
        ->take(8)
        ->values();

        // File workspace — hanya dari TA yang terhubung dengan pencari.
        $files = WorkspaceFile::where(function ($w) use ($q) {
            $w->where('original_name', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%");
        })
        ->with('mahasiswaTa.mahasiswa')
        ->get()
        ->filter(function ($f) use ($user) {
            if ($user->isAdmin()) {
                return true;
            }
            // File workspace pribadi dosen.
            if ($f->user_id) {
                return $f->user_id === $user->id;
            }
            $ta = $f->mahasiswaTa;
            if (!$ta) {
                return false;
            }
            if ($ta->isMember($user)) {
                return true;
            }
            if ($user->isDosen() && ($ta->isPembimbing($user) || $ta->isPenguji($user))) {
                return true;
            }
            if ($user->isDosen()) {
                foreach ($ta->allDosenIds() as $dosenId) {
                    if ($dosen = User::find($dosenId)) {
                        if ($user->hasDirectRelation($dosen)) {
                            return true;
                        }
                    }
                }
            }
            return false;
        })
        ->take(8)
        ->values();

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

        // Import juga fitur paket Donasi (kecuali admin).
        $user = $request->user();
        if (!$user->isAdmin() && !Feature::has('import', $user)) {
            abort(403, 'Fitur import tersedia pada paket Donasi. Silakan upgrade atau hubungi admin.');
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'pembimbing_default' => ['nullable', 'exists:users,id'],
        ]);

        $defaultPembimbing = $request->input('pembimbing_default')
            ? (int) $request->input('pembimbing_default')
            : User::role('dosen')->first()->id;

        $targetSesi = (int) $request->input('target_sesi', 7);

        $import = new MahasiswaImport($defaultPembimbing, $targetSesi);
        Excel::import($import, $request->file('file'));

        if (!empty($import->errors)) {
            return back()
                ->with('success', 'Import mahasiswa selesai (sebagian baris dilewati).')
                ->with('import_errors', $import->errors);
        }

        return back()->with('success', 'Import mahasiswa selesai.');
    }
}
