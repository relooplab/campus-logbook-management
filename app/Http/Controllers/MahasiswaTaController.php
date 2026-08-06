<?php

namespace App\Http\Controllers;

use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Services\MahasiswaDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MahasiswaTaController extends Controller
{
    public function __construct(private MahasiswaDashboardService $dashboardService)
    {
    }

    /**
     * Halaman detail mahasiswa (view dosen): profil, judul TA, fase,
     * riwayat logbook lengkap, workspace link, tombol update fase.
     * Dosen juga dapat melihat ringkasan dashboard mahasiswa (milestone,
     * progres bimbingan, achievement, statistik & streak, aktivitas 12 bulan,
     * timeline bimbingan).
     */
    public function show(Request $request, MahasiswaTa $mahasiswaTa): View
    {
        $user = $request->user();

        // Validasi jenis sesuai route (mahasiswa-ta vs mahasiswa-kp).
        $routeName = $request->route()->getName();
        $expectedJenis = str_contains($routeName, 'kp') ? MahasiswaTa::JENIS_KP : MahasiswaTa::JENIS_TA;
        abort_unless($mahasiswaTa->jenis === $expectedJenis, 404, 'Program tidak ditemukan.');

        if ($user->isAdmin()) {
            abort_unless(
                $user->isSystemAdmin() || $user->institution_id === null || $mahasiswaTa->institution_id === $user->institution_id,
                403,
                'Mahasiswa ini bukan bagian dari institusi Anda.'
            );
        } elseif ($user->isDosen() && !$mahasiswaTa->isPembimbing($user) && !$mahasiswaTa->isPenguji($user)) {
            abort(403, 'Anda bukan pembimbing atau penguji mahasiswa ini.');
        } elseif ($user->isMahasiswa() && !$mahasiswaTa->isMember($user)) {
            abort(403);
        }

        $mahasiswaTa->load([
            'mahasiswa',
            'members',
            'pembimbing1',
            'pembimbing2',
            'penguji1',
            'penguji2',
            'entries.mahasiswaTa.mahasiswa',
        ]);

        $entries = $mahasiswaTa->entries()->with('comments')->orderByDesc('created_at')->get();

        $approved = $entries->where('jenis', LogbookEntry::JENIS_LOGBOOK)->where('status', LogbookEntry::STATUS_APPROVED)->count();
        $target = $mahasiswaTa->target_sesi ?? 7;
        $percent = $target > 0 ? (int) round($approved / $target * 100) : 0;

        // ---- Data card dashboard (sama seperti dashboard mahasiswa) ----
        // Milestone fase.
        $faseKeys = array_keys($mahasiswaTa->isKp() ? MahasiswaTa::FASES_KP : MahasiswaTa::FASES);
        $faseIndex = array_search($mahasiswaTa->fase, $faseKeys, true);
        if ($faseIndex === false) $faseIndex = 0;
        $faseLabels = app(\App\Services\ProgramNamingService::class)->faseLabels($mahasiswaTa);

        // Achievement (unlocked + total) milik mahasiswa pemilik program.
        // Achievement hanya untuk TA.
        $mahasiswa = $mahasiswaTa->mahasiswa;
        $unlockedAchievements = $mahasiswaTa->isTa() && $mahasiswa ? $mahasiswa->achievements()->get() : collect();
        $unlockedCodes = $unlockedAchievements->pluck('code')->map(fn ($c) => (string) $c);
        $totalAchievements = $mahasiswaTa->isTa() ? \App\Models\Achievement::count() : 0;

        // Logbook harian hanya untuk KP.
        $logbookHarian = $mahasiswaTa->isKp()
            ? $mahasiswaTa->logbookHarian()->orderByDesc('tanggal')->get()
            : collect();

        // Statistik & streak, timeline, heatmap (aktivitas 12 bulan).
        $stats = $this->dashboardService->buildStats($mahasiswaTa, $entries);
        $timeline = $this->dashboardService->buildTimeline($mahasiswaTa, $entries);
        $heatmap = $this->dashboardService->buildHeatmap($mahasiswaTa, $entries);

        // Health indicator (self-awareness).
        $regularity = $mahasiswaTa->regularity_status;
        $regularityTooltip = $mahasiswaTa->regularity_tooltip;

        return view('mahasiswa.show', compact(
            'mahasiswaTa', 'entries', 'approved', 'target', 'percent',
            'faseKeys', 'faseIndex', 'faseLabels',
            'unlockedAchievements', 'unlockedCodes', 'totalAchievements',
            'stats', 'timeline', 'heatmap', 'regularity', 'regularityTooltip',
            'logbookHarian'
        ));
    }

    /**
     * Update fase TA (khusus dosen pembimbing P1/P2).
     * Perubahan dicatat ke audit log.
     */
    public function updateFase(Request $request, MahasiswaTa $mahasiswaTa): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isDosen(), 403, 'Hanya dosen yang dapat mengubah fase.');
        abort_unless($mahasiswaTa->isPembimbing($user), 403, 'Anda bukan pembimbing program ini.');

        $fases = $mahasiswaTa->isKp() ? MahasiswaTa::FASES_KP : MahasiswaTa::FASES;
        $validated = $request->validate([
            'fase' => ['required', 'in:'.implode(',', array_keys($fases))],
        ]);

        if ($mahasiswaTa->isTa() && $validated['fase'] === 'achievement') {
            $finalization = $mahasiswaTa->finalization;
            abort_unless($finalization && $finalization->allItemsApproved(), 403, 'Finalisasi TA belum disetujui semua item.');
        }

        $old = $mahasiswaTa->faseLabel();
        $mahasiswaTa->update(['fase' => $validated['fase']]);
        $new = $mahasiswaTa->faseLabel();

        // Audit log.
        $label = $mahasiswaTa->jenisLabel();
        Log::channel('audit')->info("Fase {$label} diubah", [
            'mahasiswa_ta_id' => $mahasiswaTa->id,
            'jenis' => $mahasiswaTa->jenis,
            'mahasiswa' => $mahasiswaTa->mahasiswa?->name,
            'oleh' => $user->name.' ('.$user->id.')',
            'dari' => $old,
            'ke' => $new,
            'waktu' => now()->toDateTimeString(),
        ]);

        return back()->with('success', "Fase diperbarui: {$old} → {$new}.");
    }
}
