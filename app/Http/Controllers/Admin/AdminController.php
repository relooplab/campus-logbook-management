<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    // ---------------------------------------------------------------- users

    public function users(Request $request): View
    {
        $query = User::query();

        if ($role = $request->query('role')) {
            $query->role($role);
        }

        if ($keyword = $request->query('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('identifier', 'like', "%{$keyword}%");
            });
        }

        $sort = $request->query('sort', 'latest');
        if ($sort === 'name') {
            $query->orderBy('name');
        } else {
            $query->latest();
        }

        $users = $query->with('roles')->paginate(20)->withQueryString();
        $roles = Role::all();

        return view('admin.users', compact('users', 'roles'));
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'identifier' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['in:admin,dosen,mahasiswa'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'identifier' => $validated['identifier'] ?? null,
            'password' => $validated['password'],
        ]);
        $user->syncRoles($validated['roles']);

        return back()->with('success', 'Pengguna berhasil dibuat.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        $user->delete();

        return back()->with('success', 'Pengguna dihapus.');
    }

    /**
     * Reset password user oleh admin.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user->update(['password' => $validated['password']]);

        return back()->with('success', "Password '{$user->name}' berhasil direset.");
    }

    // ---------------------------------------------------------------- TA & assignment

    public function tas(Request $request): View
    {
        $query = MahasiswaTa::with(['mahasiswa', 'pembimbing1', 'pembimbing2'])->withCount('entries');

        if ($request->query('keyword')) {
            $query->where('judul_ta', 'like', '%'.$request->query('keyword').'%');
        }

        if ($pembimbing = $request->query('pembimbing')) {
            $query->where(function ($q) use ($pembimbing) {
                $q->where('pembimbing_1_id', $pembimbing)->orWhere('pembimbing_2_id', $pembimbing);
            });
        }

        $tas = $query->paginate(20)->withQueryString();
        $dosenList = User::role('dosen')->orderBy('name')->get();
        $mahasiswaList = User::role('mahasiswa')->doesntHave('mahasiswaTa')->orderBy('name')->get();

        return view('admin.tas', compact('tas', 'dosenList', 'mahasiswaList'));
    }

    public function storeTa(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'judul_ta' => ['required', 'string', 'max:255'],
            'pembimbing_1_id' => ['nullable', 'exists:users,id'],
            'pembimbing_2_id' => ['nullable', 'exists:users,id'],
            'penguji_1_id' => ['nullable', 'exists:users,id'],
            'penguji_2_id' => ['nullable', 'exists:users,id'],
            'target_sesi' => ['required', 'integer', 'min:1'],
        ]);

        MahasiswaTa::create($validated);

        return back()->with('success', 'Data TA dibuat.');
    }

    public function updateTa(Request $request, MahasiswaTa $mahasiswaTa): RedirectResponse
    {
        $validated = $request->validate([
            'judul_ta' => ['required', 'string', 'max:255'],
            'pembimbing_1_id' => ['nullable', 'exists:users,id'],
            'pembimbing_2_id' => ['nullable', 'exists:users,id'],
            'penguji_1_id' => ['nullable', 'exists:users,id'],
            'penguji_2_id' => ['nullable', 'exists:users,id'],
            'target_sesi' => ['required', 'integer', 'min:1'],
            'status_ta' => ['nullable', 'in:'.implode(',', \App\Models\MahasiswaTa::STATUS_TA)],
        ]);

        $mahasiswaTa->update($validated);

        return back()->with('success', 'Assign pembimbing/penguji diperbarui.');
    }

    // ------------------------------------------------------- sidang (admin)

    public function sidangs(Request $request): View
    {
        $sidangs = \App\Models\Sidang::with(['mahasiswaTa.mahasiswa', 'penguji'])
            ->orderByDesc('tanggal')
            ->paginate(20)
            ->withQueryString();

        $mahasiswaList = MahasiswaTa::with('mahasiswa')->get();
        $dosenList = User::role('dosen')->orderBy('name')->get();

        return view('admin.sidangs', compact('sidangs', 'mahasiswaList', 'dosenList'));
    }

    public function storeSidang(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mahasiswa_ta_id' => ['required', 'exists:mahasiswa_ta,id'],
            'penguji_id' => ['required', 'exists:users,id'],
            'jenis' => ['required', 'in:'.implode(',', \App\Models\Sidang::JENISES)],
            'tanggal' => ['required', 'date'],
            'hasil' => ['nullable', 'in:'.implode(',', \App\Models\Sidang::HASILS)],
        ]);

        \App\Models\Sidang::create($validated);

        // Integrasi: jika sidang akhir & hasil lulus/lulus_revisi -> set status_ta tamat.
        if ($validated['jenis'] === \App\Models\Sidang::JENIS_SIDANG
            && in_array($validated['hasil'] ?? null, [\App\Models\Sidang::HASIL_LULUS, \App\Models\Sidang::HASIL_LULUS_REVISI], true)) {
            MahasiswaTa::where('id', $validated['mahasiswa_ta_id'])
                ->update(['status_ta' => MahasiswaTa::STATUS_TAMAT]);
        }

        return back()->with('success', 'Data sidang ditambahkan.');
    }

    public function destroySidang(Request $request, \App\Models\Sidang $sidang): RedirectResponse
    {
        $sidang->delete();

        return back()->with('success', 'Data sidang dihapus.');
    }

    /**
     * Set status_ta (aktif/tamat/nonaktif) oleh admin.
     */
    public function updateStatusTa(Request $request, MahasiswaTa $mahasiswaTa): RedirectResponse
    {
        $validated = $request->validate([
            'status_ta' => ['required', 'in:'.implode(',', MahasiswaTa::STATUS_TA)],
        ]);

        $mahasiswaTa->update(['status_ta' => $validated['status_ta']]);

        return back()->with('success', 'Status TA diperbarui.');
    }

    // ------------------------------------------------------- institusi

    public function institution(): View
    {
        $institution = Institution::active();

        return view('admin.institution', compact('institution'));
    }

    public function updateInstitution(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'institution_name' => ['required', 'string', 'max:255'],
            'faculty' => ['nullable', 'string', 'max:255'],
            'study_program' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'footer_note' => ['nullable', 'string', 'max:500'],
        ]);

        // Logo (opsional).
        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store('institution', 'local');
        }

        $institution = Institution::active();
        $institution->update($validated);
        Institution::flush();
        $institution->applyToConfig();

        return back()->with('success', 'Profil institusi diperbarui.');
    }

    // ------------------------------------------------------- review massal

    public function entries(Request $request): View
    {
        $query = \App\Models\LogbookEntry::with(['mahasiswaTa.mahasiswa']);

        $query->when($request->filled('status'), fn ($q) => $q->status($request->query('status')))
            ->when($request->filled('jenis'), fn ($q) => $q->jenis($request->query('jenis')))
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $kw = $request->query('keyword');
                $q->where(function ($qq) use ($kw) {
                    $qq->where('topik', 'like', "%{$kw}%")
                        ->orWhere('progres_kendala', 'like', "%{$kw}%")
                        ->orWhereHas('mahasiswaTa.mahasiswa', fn ($m) => $m->where('name', 'like', "%{$kw}%"));
                });
            });

        $entries = $query->latest()->paginate(20)->withQueryString();

        return view('admin.entries', compact('entries'));
    }

    // ---------------------------------------------------------------- bulk actions

    public function bulkAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:approve,revisi,delete,assign_dosen'],
        ]);

        // Assign pembimbing massal ke data TA.
        if ($validated['action'] === 'assign_dosen') {
            $request->validate(['dosen_id' => ['required', 'exists:users,id']]);

            $count = MahasiswaTa::whereIn('id', $validated['ids'])
                ->update(['pembimbing_1_id' => $request->integer('dosen_id')]);

            return back()->with('success', "{$count} data TA di-assign pembimbing 1.");
        }

        $entries = \App\Models\LogbookEntry::whereIn('id', $validated['ids']);

        switch ($validated['action']) {
            case 'approve':
                $entries->update([
                    'status' => \App\Models\LogbookEntry::STATUS_APPROVED,
                    'reviewed_at' => now(),
                ]);
                break;
            case 'revisi':
                $entries->update([
                    'status' => \App\Models\LogbookEntry::STATUS_REVISI,
                    'reviewed_at' => now(),
                ]);
                break;
            case 'delete':
                $entries->delete();
                break;
        }

        return back()->with('success', 'Aksi massal berhasil dijalankan.');
    }
}
