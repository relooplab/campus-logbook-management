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

        return view('groups.index', compact('myGroups', 'pendingInvites', 'availableGroups', 'colleagues', 'university'));
    }

    /**
     * Buat grup baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'in:universitas,fakultas,departemen,prodi'],
            'university_id' => ['required', 'exists:universities,id'],
            'faculty_id' => ['nullable', 'exists:faculties,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'study_program_id' => ['nullable', 'exists:study_programs,id'],
        ]);

        $group = Group::create([
            'name' => $validated['name'],
            'level' => $validated['level'],
            'university_id' => $validated['university_id'],
            'faculty_id' => $validated['faculty_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'study_program_id' => $validated['study_program_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        // Pembuat grup otomatis jadi owner (approved).
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $request->user()->id,
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

        // Cegah duplikat.
        $exists = $group->memberships()->where('user_id', $validated['user_id'])->exists();
        if ($exists) {
            return back()->with('error', 'Dosen tersebut sudah menjadi anggota/undangan.');
        }

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $validated['user_id'],
            'status' => 'pending',
            'role' => 'member',
        ]);

        // Notifikasi ke dosen yang diundang.
        $this->bestEffort(fn () => User::find($validated['user_id'])?->notify(
            new \App\Notifications\ActivityNotification(
                "Anda diundang ke grup '{$group->name}'.",
                route('groups.index'),
                'Undangan Grup'
            )
        ));

        return back()->with('success', 'Undangan dikirim.');
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