<?php

namespace App\Http\Controllers;

use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Persetujuan dosen bergabung (change-affiliation) ke institusi berlangganan.
 *
 * Aturan (per requirement):
 * - Node/institusi yg BERLANGGANAN → punya admin → dosen WAJIB dapat approval
 *   admin level paling rendah sebelum afiliasinya menjadi `active`.
 * - Node TIDAK berlangganan → tidak ada admin & workspace → afiliasi langsung
 *   `active` (tidak lewat sini).
 */
class AffiliationApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $request->user();
        $service = app(OrganizationalDirectoryService::class);

        // Semua permintaan afiliasi pending (dosen ingin masuk institusi).
        $rows = DB::table('user_university')
            ->where('status', OrganizationalDirectoryService::STATUS_PENDING)
            ->orderBy('updated_at')
            ->get();

        $pending = collect();
        foreach ($rows as $row) {
            $prodi = StudyProgram::find($row->study_program_id);
            if (!$prodi) {
                continue;
            }
            // Hanya tampilkan yg bisa disetujui admin ini (level paling rendah).
            if (!$service->lowestLevelAdminsForStudyProgram($prodi)->contains('id', $admin->id)) {
                continue;
            }

            $dosen = User::find($row->user_id);
            $pivot = $dosen?->universities()->where('university_id', $row->university_id)->first();

            $pending->push((object) [
                'dosen' => $dosen,
                'university' => University::find($row->university_id),
                'prodi' => $prodi,
                'requested_at' => \Illuminate\Support\Carbon::parse($row->updated_at),
                'university_id' => $row->university_id,
                'user_id' => $row->user_id,
                'pivot' => $pivot?->pivot,
            ]);
        }

        return view('affiliation-approval.index', ['pending' => $pending]);
    }

    public function approve(Request $request, User $user, University $university): RedirectResponse
    {
        $this->assertCanApprove($request, $user, $university);

        app(OrganizationalDirectoryService::class)
            ->approveAffiliation($user, $university, $request->user());

        return back()->with('success', "Afiliasi '{$user->name}' disetujui.");
    }

    public function reject(Request $request, User $user, University $university): RedirectResponse
    {
        $this->assertCanApprove($request, $user, $university);

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'max:255'],
        ]);

        app(OrganizationalDirectoryService::class)
            ->rejectAffiliation($user, $university, $request->user(), $validated['alasan']);

        return back()->with('success', "Afiliasi '{$user->name}' ditolak.");
    }

    private function assertCanApprove(Request $request, User $user, University $university): void
    {
        $pivot = $user->universities()->where('university_id', $university->id)->first();
        abort_unless($pivot, 404, 'Afiliasi tidak ditemukan.');
        abort_unless($pivot->pivot->status === OrganizationalDirectoryService::STATUS_PENDING, 400, 'Afiliasi bukan status pending.');

        $prodi = StudyProgram::find($pivot->pivot->study_program_id);
        abort_unless($prodi, 422, 'Afiliasi tidak memiliki prodi.');

        $approvers = app(OrganizationalDirectoryService::class)
            ->lowestLevelAdminsForStudyProgram($prodi);
        abort_unless($approvers->contains('id', $request->user()->id), 403, 'Anda tidak berwenang menyetujui afiliasi ini.');
    }
}
