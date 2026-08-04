<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Grup & cross-link antar dosen (dengan approval).
 * Dosen membuat grup di level universitas/fakultas/departemen/prodi,
 * mengundang dosen lain dari universitas yang sama, dan yang diundang
 * harus menyetujui (approve) sebelum menjadi anggota.
 */
class GroupController extends Controller
{
    /**
     * Daftar grup yang diikuti user + grup yang tersedia di universitas user.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Grup yang diikuti user (approved).
        $myGroups = Group::whereHas('memberships', fn ($q) => $q->where('user_id', $user->id)->where('status', 'approved'))
            ->with(['university', 'members'])
            ->get();

        // Undangan pending untuk user.
        $pendingInvites = Group::whereHas('memberships', fn ($q) => $q->where('user_id', $user->id)->where('status', 'pending'))
            ->with(['university', 'creator'])
            ->get();

        // Grup yang tersedia di universitas user (untuk join).
        $university = $user->primaryUniversity();
        $availableGroups = $university
            ? Group::where('university_id', $university->id)
                ->whereDoesntHave('memberships', fn ($q) => $q->where('user_id', $user->id))
                ->with(['university', 'members'])
                ->get()
            : collect();

        // Dosen dari universitas yang sama (untuk diundang).
        $colleagues = $university
            ? User::whereHas('universities', fn ($q) => $q->where('university_id', $university->id))
                ->where('id', '!=', $user->id)
                ->role('dosen')
                ->orderBy('name')
                ->get()
            : collect();

        // Data organisasi untuk form buat grup (dropdown sesuai level).
        $faculties = $university ? $university->faculties()->orderBy('name')->get() : collect();
        $departments = $university
            ? \App\Models\Department::whereHas('faculty', fn ($q) => $q->where('university_id', $university->id))->orderBy('name')->get()
            : collect();
        $studyPrograms = $university
            ? \App\Models\StudyProgram::whereHas('department.faculty', fn ($q) => $q->where('university_id', $university->id))->orderBy('name')->get()
            : collect();

        return view('groups.index', compact('myGroups', 'pendingInvites', 'availableGroups', 'colleagues', 'university', 'faculties', 'departments', 'studyPrograms'));
    }

    /**
     * Buat grup baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'in:universitas,fakultas,departemen,prodi'],
            'university_id' => ['required', 'exists:universities,id'],
            'faculty_id' => ['nullable', 'exists:faculties,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'study_program_id' => ['nullable', 'exists:study_programs,id'],
        ]);

        // Bug 3: Pastikan university_id milik user yang membuat grup.
        $ownsUniversity = $user->universities()
            ->where('university_id', $validated['university_id'])
            ->exists();
        abort_unless($ownsUniversity, 403, 'Anda tidak terafiliasi dengan universitas tersebut.');

        // Bug 4: Konsistensi level — field wajib/prohibited sesuai level.
        $level = $validated['level'];
        $facultyId = $validated['faculty_id'] ?? null;
        $departmentId = $validated['department_id'] ?? null;
        $studyProgramId = $validated['study_program_id'] ?? null;

        if ($level === 'universitas') {
            // Tidak boleh ada faculty/department/prodi.
            abort_if($facultyId || $departmentId || $studyProgramId, 422, 'Level universitas tidak boleh memiliki fakultas/departemen/prodi.');
        } elseif ($level === 'fakultas') {
            abort_unless($facultyId, 422, 'Level fakultas wajib memilih fakultas.');
            abort_if($departmentId || $studyProgramId, 422, 'Level fakultas tidak boleh memiliki departemen/prodi.');
        } elseif ($level === 'departemen') {
            abort_unless($departmentId, 422, 'Level departemen wajib memilih departemen.');
            abort_if($studyProgramId, 422, 'Level departemen tidak boleh memiliki prodi.');
        } elseif ($level === 'prodi') {
            abort_unless($studyProgramId, 422, 'Level prodi wajib memilih program studi.');
        }

        $group = Group::create([
            'name' => $validated['name'],
            'level' => $level,
            'university_id' => $validated['university_id'],
            'faculty_id' => $facultyId,
            'department_id' => $departmentId,
            'study_program_id' => $studyProgramId,
            'created_by' => $user->id,
        ]);

        // Pembuat grup otomatis jadi owner (approved).
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'approved',
            'role' => 'owner',
        ]);

        return redirect()->route('groups.index')
            ->with('success', "Grup '{$group->name}' berhasil dibuat.");
    }

    /**
     * Undang dosen lain ke grup.
     */
    public function invite(Request $request, Group $group): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        // Hanya owner/member approved yang bisa mengundang.
        $isMember = $group->memberships()
            ->where('user_id', $request->user()->id)
            ->where('status', 'approved')
            ->exists();
        abort_unless($isMember, 403, 'Anda bukan anggota grup ini.');

        // Bug 2: Pastikan yang diundang adalah dosen.
        $invitee = User::find($validated['user_id']);
        abort_unless($invitee && $invitee->isDosen(), 422, 'Hanya dosen yang dapat diundang ke grup.');

        // Bug 2: Pastikan dosen yang diundang dari universitas yang sama dengan grup.
        $sameUniversity = $invitee->universities()
            ->where('university_id', $group->university_id)
            ->exists();
        abort_unless($sameUniversity, 422, 'Dosen yang diundang harus dari universitas yang sama dengan grup.');

        // Bug 5: Cegah duplikat hanya untuk status pending/approved.
        // Jika ada membership rejected, izinkan undang ulang (update jadi pending).
        $existing = $group->memberships()->where('user_id', $validated['user_id'])->first();
        if ($existing) {
            if (in_array($existing->status, ['pending', 'approved'], true)) {
                return back()->with('error', 'Dosen tersebut sudah menjadi anggota/undangan.');
            }
            // Status rejected → undang ulang.
            $existing->update(['status' => 'pending', 'role' => 'member']);
        } else {
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $validated['user_id'],
                'status' => 'pending',
                'role' => 'member',
            ]);
        }

        // Notifikasi ke dosen yang diundang.
        $this->bestEffort(fn () => $invitee->notify(
            new \App\Notifications\ActivityNotification(
                "Anda diundang ke grup '{$group->name}'.",
                route('groups.index'),
                'Undangan Grup'
            )
        ));

        return back()->with('success', 'Undangan dikirim.');
    }

    /**
     * Gabung langsung ke grup yang tersedia (tanpa undangan).
     * Hanya untuk grup di universitas yang sama dengan user.
     */
    public function join(Request $request, Group $group): RedirectResponse
    {
        $user = $request->user();

        // Pastikan user dosen.
        abort_unless($user->isDosen(), 403, 'Hanya dosen yang dapat bergabung ke grup.');

        // Pastikan grup dari universitas yang sama dengan user.
        $sameUniversity = $user->universities()
            ->where('university_id', $group->university_id)
            ->exists();
        abort_unless($sameUniversity, 403, 'Anda tidak terafiliasi dengan universitas grup ini.');

        // Cek membership yang sudah ada.
        $existing = $group->memberships()->where('user_id', $user->id)->first();
        if ($existing) {
            if ($existing->status === 'approved') {
                return back()->with('error', 'Anda sudah menjadi anggota grup ini.');
            }
            if ($existing->status === 'pending') {
                return back()->with('error', 'Anda sudah memiliki undangan pending untuk grup ini.');
            }
            // Status rejected → izinkan join ulang.
            $existing->update(['status' => 'approved', 'role' => 'member']);
        } else {
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'status' => 'approved',
                'role' => 'member',
            ]);
        }

        return back()->with('success', "Anda bergabung ke grup '{$group->name}'.");
    }

    /**
     * Setujui undangan grup.
     */
    public function approve(Request $request, Group $group): RedirectResponse
    {
        $membership = $group->memberships()
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->first();
        abort_unless($membership, 403, 'Tidak ada undangan pending untuk Anda.');

        $membership->update(['status' => 'approved']);

        return back()->with('success', "Anda bergabung ke grup '{$group->name}'.");
    }

    /**
     * Tolak undangan grup.
     */
    public function reject(Request $request, Group $group): RedirectResponse
    {
        $membership = $group->memberships()
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->first();
        abort_unless($membership, 403, 'Tidak ada undangan pending untuk Anda.');

        $membership->update(['status' => 'rejected']);

        return back()->with('success', 'Undangan grup ditolak.');
    }
}