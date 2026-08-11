<?php

namespace App\Http\Controllers;

use App\Models\DosenChangeApproval;
use App\Models\DosenChangeRequest;
use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Halaman "Profil Akademik" mahasiswa: ringkasan program (TA/KP) + dosen
 * (pembimbing/penguji) + pengusulan/mengganti penguji yang perlu disetujui
 * SEMUA dosen terkait.
 */
class ProfilAkademikController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $ta = $user->mahasiswaTa;      // program TA
        $kp = $user->allPrograms()->where('jenis', 'kp')->first(); // program KP

        $programs = collect(compact('ta', 'kp'))->filter();

        // Daftar dosen aktif untuk dropdown usul penguji (bebas dari PT mana saja).
        $dosenList = User::role('dosen')
            ->where('registration_status', 'active')
            ->orderBy('name')
            ->get();

        // Permintaan penguji aktif (pending) + riwayat per program.
        $pendingRequests = DosenChangeRequest::whereIn('mahasiswa_ta_id', $programs->pluck('id'))
            ->where('status', DosenChangeRequest::STATUS_PENDING)
            ->with(['proposedDosen', 'mahasiswaTa'])
            ->orderByDesc('created_at')
            ->get();

        $historyRequests = DosenChangeRequest::whereIn('mahasiswa_ta_id', $programs->pluck('id'))
            ->whereIn('status', [DosenChangeRequest::STATUS_APPROVED, DosenChangeRequest::STATUS_REJECTED])
            ->with(['proposedDosen', 'mahasiswaTa'])
            ->orderByDesc('created_at')
            ->get();

        return view('profile.profil-akademik', compact(
            'ta', 'kp', 'programs', 'dosenList', 'pendingRequests', 'historyRequests'
        ));
    }

    /**
     * Usulkan/mengganti penguji untuk sebuah program (TA/KP) yang aktif.
     */
    public function proposePenguji(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isMahasiswa(), 403);

        $validated = $request->validate([
            'mahasiswa_ta_id' => ['required', 'exists:mahasiswa_ta,id'],
            'proposed_dosen_id' => ['required', 'exists:users,id'],
        ]);

        $program = MahasiswaTa::findOrFail($validated['mahasiswa_ta_id']);

        // Hanya pemilik/anggota program yang boleh mengusulkan.
        abort_unless($program->isMember($user), 403, 'Anda bukan anggota program ini.');

        // Hanya program aktif yang bisa menerima usulan penguji.
        abort_unless($program->status_ta === MahasiswaTa::STATUS_AKTIF, 422, 'Program tidak aktif — tidak dapat mengusulkan penguji.');

        $dosenKu = User::findOrFail($validated['proposed_dosen_id']);
        abort_unless($dosenKu->isDosen() && $dosenKu->registration_status === 'active', 422, 'Dosen yang dipilih tidak valid atau tidak aktif.');

        // Cegah duplikat: penguji yang diusulkan tidak boleh sudah jadi dosen di TA ini.
        $existing = array_unique(array_filter([
            $program->pembimbing_1_id,
            $program->pembimbing_2_id,
            $program->penguji_1_id,
            $program->penguji_2_id,
        ]));
        if (in_array($dosenKu->id, $existing, true)) {
            return back()->with('error', 'Dosen tersebut sudah terlibat di program ini (pembimbing/penguji).');
        }

        // Satu permintaan PENDING per program; rejected/approved membuka usulan baru.
        $hasPending = DosenChangeRequest::where('mahasiswa_ta_id', $program->id)
            ->where('status', DosenChangeRequest::STATUS_PENDING)
            ->exists();
        if ($hasPending) {
            return back()->with('error', 'Sudah ada permintaan penguji yang menunggu persetujuan untuk program ini.');
        }

        // Tentukan kolom target: penguji_1 dulu, lalu penguji_2.
        $role = $program->penguji_1_id ? DosenChangeRequest::ROLE_PENGUJI_2 : DosenChangeRequest::ROLE_PENGUJI_1;

        DB::transaction(function () use ($program, $user, $dosenKu, $role) {
            $change = DosenChangeRequest::create([
                'mahasiswa_ta_id' => $program->id,
                'requester_id' => $user->id,
                'proposed_role' => $role,
                'proposed_dosen_id' => $dosenKu->id,
                'status' => DosenChangeRequest::STATUS_PENDING,
            ]);

            // Semua approver: pembimbing & penguji yang ada + calon penguji baru.
            foreach ($change->requiredApproverIds() as $dosenId) {
                DosenChangeApproval::create([
                    'request_id' => $change->id,
                    'dosen_id' => $dosenId,
                    'status' => DosenChangeApproval::STATUS_PENDING,
                ]);
            }

            // Notifikasi ke semua approver.
            foreach ($change->requiredApproverIds() as $dosenId) {
                if ($approver = User::find($dosenId)) {
                    $this->bestEffort(fn () => $approver->notify(new \App\Notifications\ActivityNotification(
                        "Mahasiswa '{$user->name}' mengusulkan penguji '{$dosenKu->name}' untuk program ".$program->jenisLabel().'.',
                        route('approval.index'),
                        'Permintaan Penguji Baru',
                    )));
                }
            }
        });

        \App\Support\Audit::log('Mahasiswa mengusulkan penguji', [
            'mahasiswa_ta_id' => $program->id,
            'proposed_dosen_id' => $dosenKu->id,
        ]);

        return back()->with('success', 'Usulan penguji dikirim dan menunggu persetujuan semua dosen terkait.');
    }
}