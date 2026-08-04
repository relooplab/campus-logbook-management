<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\MahasiswaTa;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPlanOverride;
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

        // Admin biasa tidak dapat melihat/memfilter user dengan role system_admin.
        $isSystemAdmin = $request->user()->isSystemAdmin();
        if (!$isSystemAdmin) {
            $query->whereDoesntHave('roles', fn ($q) => $q->where('name', 'system_admin'));
        }

        if ($role = $request->query('role')) {
            // Admin biasa tidak dapat memfilter role system_admin.
            if ($role === 'system_admin' && !$isSystemAdmin) {
                $role = null;
            }
            if ($role) {
                $query->role($role);
            }
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
        $roles = $isSystemAdmin ? Role::all() : Role::where('name', '!=', 'system_admin')->get();

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

        // Hanya system admin yang dapat membuat user dengan role admin.
        if (in_array('admin', $validated['roles'], true) && !$request->user()->isSystemAdmin()) {
            return back()->with('error', 'Hanya System Admin yang dapat membuat akun admin.');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'identifier' => $validated['identifier'] ?? null,
            'password' => $validated['password'],
        ]);
        $user->syncRoles($validated['roles']);

        return back()->with('success', 'Pengguna berhasil dibuat.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        // Admin biasa tidak dapat menghapus user dengan role admin/system_admin.
        if (!$request->user()->isSystemAdmin() && ($user->isAdmin() || $user->isSystemAdmin())) {
            return back()->with('error', 'Hanya System Admin yang dapat menghapus akun admin.');
        }

        $user->delete();

        return back()->with('success', 'Pengguna dihapus.');
    }

    // ------------------------------------------------------- persetujuan dosen

    /**
     * Daftar dosen yang mendaftar mandiri & menunggu persetujuan admin.
     */
    public function dosenApprovals(): View
    {
        $pending = User::role('dosen')
            ->where('registration_status', 'pending')
            ->orderBy('created_at')
            ->get();

        return view('admin.dosen-approvals', compact('pending'));
    }

    /**
     * Setujui akun dosen (dari registrasi mandiri).
     */
    public function approveDosen(User $dosen): RedirectResponse
    {
        abort_if($dosen->registration_status !== 'pending', 400, 'Status dosen bukan pending.');

        $dosen->update(['registration_status' => 'approved']);

        return back()->with('success', "Dosen '{$dosen->name}' disetujui.");
    }

    /**
     * Tolak registrasi dosen.
     */
    public function rejectDosen(User $dosen): RedirectResponse
    {
        $dosen->update(['registration_status' => 'rejected']);

        return back()->with('success', "Registrasi '{$dosen->name}' ditolak.");
    }

    /**
     * Reset password user oleh admin.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        // Admin biasa tidak dapat reset password user dengan role admin/system_admin.
        if (!$request->user()->isSystemAdmin() && ($user->isAdmin() || $user->isSystemAdmin())) {
            return back()->with('error', 'Hanya System Admin yang dapat reset password akun admin.');
        }

        $user->update(['password' => $validated['password']]);

        return back()->with('success', "Password '{$user->name}' berhasil direset.");
    }

    // ------------------------------------------------------- system admin

    /**
     * Daftar semua user dengan role admin (dikelola oleh system admin).
     */
    public function systemAdmins(): View
    {
        $admins = User::role('admin')
            ->with('roles')
            ->orderBy('name')
            ->get();

        return view('admin.system-admins', compact('admins'));
    }

    /**
     * Buat akun admin baru (hanya system admin).
     */
    public function storeSystemAdmin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'identifier' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'identifier' => $validated['identifier'] ?? null,
            'password' => $validated['password'],
        ]);
        $user->syncRoles(['admin']);

        return back()->with('success', 'Akun admin berhasil dibuat.');
    }

    /**
     * Hapus akun admin (hanya system admin).
     */
    public function destroySystemAdmin(Request $request, User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        // Tidak dapat menghapus system admin lain.
        if ($user->isSystemAdmin()) {
            return back()->with('error', 'Tidak dapat menghapus akun System Admin.');
        }

        // Hanya user dengan role admin (bukan system_admin) yang bisa dihapus di sini.
        if (!$user->hasRole('admin')) {
            return back()->with('error', 'User ini bukan akun admin.');
        }

        $user->delete();

        return back()->with('success', 'Akun admin dihapus.');
    }

    /**
     * Reset password akun admin (hanya system admin).
     */
    public function resetSystemAdminPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        if (!$user->hasRole('admin')) {
            return back()->with('error', 'User ini bukan akun admin.');
        }

        $user->update(['password' => $validated['password']]);

        return back()->with('success', "Password admin '{$user->name}' berhasil direset.");
    }

    // ---------------------------------------------------------------- TA & assignment

    public function tas(Request $request): View
    {
        $jenis = $request->query('jenis', 'ta');
        $query = MahasiswaTa::with(['mahasiswa', 'pembimbing1', 'pembimbing2', 'members'])->withCount('entries');

        if ($jenis === 'kp' || $jenis === 'ta') {
            $query->jenis($jenis);
        }

        if ($request->query('keyword')) {
            $kw = $request->query('keyword');
            $query->where(function ($q) use ($kw) {
                $q->where('judul_ta', 'like', "%{$kw}%")
                    ->orWhere('tempat_kp', 'like', "%{$kw}%");
            });
        }

        if ($pembimbing = $request->query('pembimbing')) {
            $query->where(function ($q) use ($pembimbing) {
                $q->where('pembimbing_1_id', $pembimbing)->orWhere('pembimbing_2_id', $pembimbing);
            });
        }

        $tas = $query->paginate(20)->withQueryString();
        $dosenList = User::role('dosen')->orderBy('name')->get();
        $mahasiswaList = User::role('mahasiswa')
            ->whereDoesntHave('mahasiswaPrograms', fn ($q) => $q->where('jenis', $jenis))
            ->orderBy('name')
            ->get();

        return view('admin.tas', compact('tas', 'dosenList', 'mahasiswaList', 'jenis'));
    }

    public function storeTa(Request $request): RedirectResponse
    {
        $jenis = $request->input('jenis', MahasiswaTa::JENIS_TA);
        $isKp = $jenis === MahasiswaTa::JENIS_KP;

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id',
                \Illuminate\Validation\Rule::unique('mahasiswa_ta', 'user_id')
                    ->where(fn ($q) => $q->where('jenis', $jenis)),
            ],
            'jenis' => ['required', 'in:'.implode(',', MahasiswaTa::JENISES)],
            'judul_ta' => ['nullable', 'string', 'max:255'],
            'tempat_kp' => ['nullable', 'string', 'max:255'],
            'pembimbing_1_id' => ['nullable', 'exists:users,id', $this->roleRule('dosen')],
            'pembimbing_2_id' => ['nullable', 'exists:users,id', $this->roleRule('dosen')],
            'pembimbing_lapangan' => [$isKp ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'penguji_1_id' => ['nullable', 'exists:users,id', $this->roleRule('dosen')],
            'penguji_2_id' => ['nullable', 'exists:users,id', $this->roleRule('dosen')],
            'target_sesi' => ['required', 'integer', 'min:1'],
            'periode_mulai' => ['nullable', 'date'],
            'periode_selesai' => ['nullable', 'date', 'after_or_equal:periode_mulai'],
            'member_ids' => [$isKp ? 'nullable' : 'prohibited', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id', $this->roleRule('mahasiswa')],
        ]);

        $program = MahasiswaTa::create($validated);

        // Anggota kelompok tambahan (khusus KP).
        if ($isKp && !empty($validated['member_ids'])) {
            $program->members()->sync(array_diff($validated['member_ids'], [$program->user_id]));
        }

        return back()->with('success', 'Data '.($isKp ? 'KP' : 'TA').' dibuat.');
    }

    public function updateTa(Request $request, MahasiswaTa $mahasiswaTa): RedirectResponse
    {
        $isKp = $mahasiswaTa->isKp();

        $validated = $request->validate([
            'judul_ta' => ['nullable', 'string', 'max:255'],
            'tempat_kp' => ['nullable', 'string', 'max:255'],
            'pembimbing_1_id' => ['nullable', 'exists:users,id', $this->roleRule('dosen')],
            'pembimbing_2_id' => ['nullable', 'exists:users,id', $this->roleRule('dosen')],
            'pembimbing_lapangan' => [$isKp ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'penguji_1_id' => ['nullable', 'exists:users,id', $this->roleRule('dosen')],
            'penguji_2_id' => ['nullable', 'exists:users,id', $this->roleRule('dosen')],
            'target_sesi' => ['required', 'integer', 'min:1'],
            'periode_mulai' => ['nullable', 'date'],
            'periode_selesai' => ['nullable', 'date', 'after_or_equal:periode_mulai'],
            'status_ta' => ['nullable', 'in:'.implode(',', \App\Models\MahasiswaTa::STATUS_TA)],
            'member_ids' => [$isKp ? 'nullable' : 'prohibited', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id', $this->roleRule('mahasiswa')],
        ]);

        $mahasiswaTa->update($validated);

        // Sinkronkan anggota kelompok tambahan (khusus KP).
        if ($isKp) {
            $memberIds = array_diff($validated['member_ids'] ?? [], [$mahasiswaTa->user_id]);
            $mahasiswaTa->members()->sync($memberIds);
        }

        return back()->with('success', 'Data '.($isKp ? 'KP' : 'TA').' diperbarui.');
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

        return back()->with('success', 'Status program diperbarui.');
    }

    // ------------------------------------------------------- paket & override

    /**
     * Form pengaturan paket (free/donasi) + override admin per user.
     */
    public function planSettings(User $user): View
    {
        $plans = Plan::where('is_active', true)->orderBy('price')->get();
        $activePlan = $user->activePlan();
        $override = $user->planOverride;

        return view('admin.plan-settings', compact('user', 'plans', 'activePlan', 'override'));
    }

    /**
     * Simpan paket & override admin untuk user.
     */
    public function updatePlanSettings(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'allow_export' => ['nullable', 'boolean'],
            'allow_import' => ['nullable', 'boolean'],
            'storage_limit_mb' => ['nullable', 'integer', 'min:0', 'max:1048576'],
        ]);

        // Set subscription aktif (nonaktifkan yang lain).
        Subscription::where('user_id', $user->id)->update(['status' => 'cancelled']);
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $validated['plan_id'],
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        // Override admin (nullable = ikut plan).
        UserPlanOverride::updateOrCreate(
            ['user_id' => $user->id],
            [
                'allow_export' => $request->boolean('allow_export') ? true : null,
                'allow_import' => $request->boolean('allow_import') ? true : null,
                'storage_limit_mb' => $validated['storage_limit_mb'] ?: null,
            ]
        );

        return back()->with('success', "Paket '{$user->name}' diperbarui.");
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
            // Pengaturan upload (bisa diisi admin).
            'max_upload_size_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'allowed_file_types' => ['required', 'string', 'max:255'],
            'seminar_hardcopy_note' => ['nullable', 'string'],
            // Pengaturan email (SMTP) — bisa diisi admin.
            'mail_mailer' => ['nullable', 'string', 'max:20', 'in:smtp,log,array,sendmail,mailgun,ses,postmark,resend'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'max:20', 'in:ssl,tls,null'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $institution = Institution::active();

        // Logo (opsional). Hapus logo lama agar tidak menumpuk di disk.
        if ($request->hasFile('logo')) {
            if ($institution->logo_path) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($institution->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('institution', 'local');
        }

        $institution->update($validated);
        Institution::flush();
        $institution->applyToConfig();

        return back()->with('success', 'Profil institusi & pengaturan email diperbarui.');
    }

    /**
     * Kirim email uji untuk memverifikasi konfigurasi SMTP.
     */
    public function testMail(Request $request): RedirectResponse
    {
        $institution = Institution::active();
        $institution->applyToConfig();

        $to = $request->input('to') ?: $request->user()->email;

        try {
            \Illuminate\Support\Facades\Mail::raw(
                'Ini adalah email uji dari '.config('app.name').'. Konfigurasi SMTP berhasil!',
                function ($message) use ($to) {
                    $message->to($to)
                        ->subject('Email Uji — '.config('app.name'));
                }
            );

            return back()->with('success', 'Email uji berhasil dikirim ke '.$to.'.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengirim email uji: '.$e->getMessage());
        }
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
            'feedback_dosen' => ['required_if:action,revisi', 'string', 'min:20'],
        ]);

        // Assign pembimbing massal ke data TA.
        if ($validated['action'] === 'assign_dosen') {
            $request->validate(['dosen_id' => ['required', 'exists:users,id']]);

            $dosen = User::find($request->integer('dosen_id'));
            abort_unless($dosen && $dosen->hasRole('dosen'), 422, 'User yang dipilih bukan dosen.');

            $count = MahasiswaTa::whereIn('id', $validated['ids'])
                ->update(['pembimbing_1_id' => $dosen->id]);

            return back()->with('success', "{$count} data TA di-assign pembimbing 1.");
        }

        if ($validated['action'] === 'delete') {
            $count = \App\Models\LogbookEntry::whereIn('id', $validated['ids'])->delete();

            return back()->with('success', "{$count} entri dihapus.");
        }

        // approve/revisi: hanya entri yang benar-benar menunggu review, dengan
        // efek samping yang sama seperti alur review satuan (resolve komentar,
        // notifikasi, evaluasi achievement) agar mahasiswa tetap diberi tahu.
        $entries = \App\Models\LogbookEntry::whereIn('id', $validated['ids'])
            ->where('status', \App\Models\LogbookEntry::STATUS_SUBMITTED)
            ->get();

        foreach ($entries as $entry) {
            if ($validated['action'] === 'approve') {
                $entry->update([
                    'status' => \App\Models\LogbookEntry::STATUS_APPROVED,
                    'reviewed_at' => now(),
                ]);
                $this->resolveCommentsOnApproval($entry);

                $this->bestEffort(fn () => \App\Events\EntryStatusChanged::dispatch($entry, 'Entri Anda telah disetujui oleh pembimbing.'));
                $entry->notifyParties(
                    'Entri '.($entry->jenis === 'revisi' ? 'revisi' : 'logbook sesi '.$entry->sesi_ke).' telah disetujui.',
                    route('logbook.show', $entry),
                    'Entri Disetujui',
                );

                if ($owner = $entry->mahasiswaTa?->mahasiswa) {
                    app(\App\Services\AchievementService::class)->evaluateForUser($owner);
                }
            } else {
                $entry->update([
                    'status' => \App\Models\LogbookEntry::STATUS_REVISI,
                    'feedback_dosen' => $validated['feedback_dosen'],
                    'reviewed_at' => now(),
                ]);

                $this->bestEffort(fn () => \App\Events\EntryStatusChanged::dispatch($entry, 'Entri Anda diminta revisi: '.$validated['feedback_dosen']));
                $entry->notifyParties(
                    'Entri Anda diminta revisi: '.$validated['feedback_dosen'],
                    route('logbook.show', $entry),
                    'Permintaan Revisi',
                );
            }
        }

        return back()->with('success', $entries->count().' entri berhasil diproses.');
    }

    /**
     * Aturan validasi: pastikan user_id yang dipilih benar memiliki role tertentu
     * (mis. pembimbing/penguji harus dosen, member_ids harus mahasiswa).
     */
    private function roleRule(string $role): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($role) {
            if ($value && !User::find($value)?->hasRole($role)) {
                $fail("User yang dipilih untuk {$attribute} bukan {$role}.");
            }
        };
    }

    /** Resolve semua komentar PDF (entri ini & induknya) saat entri disetujui. */
    private function resolveCommentsOnApproval(\App\Models\LogbookEntry $logbook): void
    {
        $entries = collect([$logbook, $logbook->parentEntry])->filter();

        foreach ($entries as $entry) {
            $entry->comments()
                ->where('resolution_status', '!=', \App\Models\PdfComment::STATUS_RESOLVED)
                ->get()
                ->each(function ($comment) {
                    $comment->setResolutionStatus(\App\Models\PdfComment::STATUS_RESOLVED);
                    $comment->save();
                });
        }
    }
}
