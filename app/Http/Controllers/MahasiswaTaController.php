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

        if ($user->isAdmin()) {
            // admin boleh lihat semua
        } elseif ($user->isDosen() && !$mahasiswaTa->isPembimbing($user) && !$mahasiswaTa->isPenguji($user)) {
            abort(403, 'Anda bukan pembimbing atau penguji mahasiswa ini.');
        } elseif ($user->isMahasiswa() && $user->id !== $mahasiswaTa->user_id) {
            abort(403);
        }

        $mahasiswaTa->load([
            'mahasiswa',
            'pembimbing1',
            'pembimbing2',
            'penguji1',
            'penguji2',
            'entries.mahasiswaTa.mahasiswa',
        ]);

        $entries = $mahasiswaTa->entries()->with('comments')->orderByDesc('created_at')->get();

        $approved = $entries->where('status', LogbookEntry::STATUS_APPROVED)->count();
        $target = $mahasiswaTa->target_sesi ?? 7;
        $percent = $target > 0 ? (int) round($approved / $target * 100) : 0;

        // ---- Data card dashboard (sama seperti dashboard mahasiswa) ----
        // Milestone fase.
        $faseKeys = array_keys(MahasiswaTa::FASES);
        $faseIndex = array_search($mahasiswaTa->fase, $faseKeys, true);
        if ($faseIndex === false) $faseIndex = 0;

        // Achievement (unlocked + total) milik mahasiswa pemilik TA.
        $mahasiswa = $mahasiswaTa->mahasiswa;
        $unlockedAchievements = $mahasiswa ? $mahasiswa->achievements()->get() : collect();
        $unlockedCodes = $unlockedAchievements->pluck('code')->map(fn ($c) => (string) $c);
        $totalAchievements = \App\Models\Achievement::count();

        // Statistik & streak, timeline, heatmap (aktivitas 12 bulan).
        $stats = $this->dashboardService->buildStats($mahasiswaTa, $entries);
        $timeline = $this->dashboardService->buildTimeline($mahasiswaTa, $entries);
        $heatmap = $this->dashboardService->buildHeatmap($mahasiswaTa, $entries);

        // Health indicator (self-awareness).
        $regularity = $mahasiswaTa->regularity_status;
        $regularityTooltip = $mahasiswaTa->regularity_tooltip;

        return view('mahasiswa.show', compact(
            'mahasiswaTa', 'entries', 'approved', 'target', 'percent',
            'faseKeys', 'faseIndex',
            'unlockedAchievements', 'unlockedCodes', 'totalAchievements',
            'stats', 'timeline', 'heatmap', 'regularity', 'regularityTooltip'
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
        abort_unless($mahasiswaTa->isPembimbing($user), 403, 'Anda bukan pembimbing TA ini.');

        $validated = $request->validate([
            'fase' => ['required', 'in:'.implode(',', array_keys(MahasiswaTa::FASES))],
        ]);

        $old = $mahasiswaTa->faseLabel();
        $mahasiswaTa->update(['fase' => $validated['fase']]);
        $new = $mahasiswaTa->faseLabel();

        // Audit log.
        Log::channel('audit')->info('Fase TA diubah', [
            'mahasiswa_ta_id' => $mahasiswaTa->id,
            'mahasiswa' => $mahasiswaTa->mahasiswa?->name,
            'oleh' => $user->name.' ('.$user->id.')',
            'dari' => $old,
            'ke' => $new,
            'waktu' => now()->toDateTimeString(),
        ]);

        return back()->with('success', "Fase diperbarui: {$old} → {$new}.");
    }
}