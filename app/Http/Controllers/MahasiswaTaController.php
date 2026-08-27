<?php

namespace App\Http\Controllers;

use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\User;
use App\Notifications\ActivityNotification;
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

        // Daftar dosen aktif untuk dropdown ganti penguji (pembimbing).
        $dosenList = User::role('dosen')
            ->whereIn('registration_status', ['active', 'approved'])
            ->orderBy('name')
            ->get();

        // Kandidat anggota untuk tombol "Gabung mahasiswa" (dosen pembimbing).
        $eligibleMembers = ($mahasiswaTa->isKp() && $user->isDosen())
            ? $mahasiswaTa->eligibleMemberCandidates()
            : collect();

        return view('mahasiswa.show', compact(
            'mahasiswaTa', 'entries', 'approved', 'target', 'percent',
            'faseKeys', 'faseIndex', 'faseLabels',
            'unlockedAchievements', 'unlockedCodes', 'totalAchievements',
            'stats', 'timeline', 'heatmap', 'regularity', 'regularityTooltip',
            'logbookHarian', 'dosenList', 'eligibleMembers'
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

    /**
     * Gabungkan mahasiswa ke kelompok KP (oleh dosen pembimbing).
     *
     * Dipakai kasus produksi di mana mahasiswa terpisah padahal seharusnya
     * satu kelompok: mahasiswa dijadikan anggota kelompok ini dan program KP
     * lamanya dinonaktifkan (histori data tetap tersimpan, tidak dipindah).
     */
    public function gabungkanAnggota(Request $request, MahasiswaTa $mahasiswaTa): RedirectResponse
    {
        $user = $request->user();

        abort_unless($mahasiswaTa->isKp(), 404, 'Program bukan KP.');
        abort_unless($user->isDosen() && $mahasiswaTa->isPembimbing($user), 403, 'Hanya pembimbing yang dapat menggabungkan anggota.');

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $candidate = User::findOrFail($validated['user_id']);

        if ($candidate->id === $mahasiswaTa->user_id || $mahasiswaTa->members()->whereKey($candidate->id)->exists()) {
            return back()->with('error', 'Mahasiswa tersebut sudah menjadi anggota kelompok ini.');
        }

        if (! MahasiswaTa::kpCandidateEligible($candidate, $mahasiswaTa->id)) {
            return back()->with('error', 'Mahasiswa tersebut telah menjadi anggota kelompok KP lain dan tidak dapat digabung.');
        }

        $mahasiswaTa->members()->attach($candidate->id);
        MahasiswaTa::deactivateKpExcept($candidate, $mahasiswaTa->id);

        $this->bestEffort(fn () => $candidate->notify(new ActivityNotification(
            "Anda telah digabungkan ke kelompok KP '".($mahasiswaTa->tempat_kp ?: 'Kerja Praktik')."'.",
            route('dashboard'),
            'Gabung Kelompok KP',
        )));

        return back()->with('success', "{$candidate->name} ditambahkan sebagai anggota kelompok.");
    }

    /**
     * Ganti dosen penguji langsung oleh pembimbing (atau admin), tanpa alur
     * persetujuan multi-approver. Digunakan saat slot penguji sudah terisi atau
     * pembimbing perlu menyesuaikan penguji program — otoritas mirip admin.
     */
    public function updatePenguji(Request $request, MahasiswaTa $mahasiswaTa): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isDosen() || $user->isAdmin(), 403, 'Hanya dosen yang dapat mengubah penguji.');
        abort_unless($user->isAdmin() || $mahasiswaTa->isPembimbing($user), 403, 'Anda bukan pembimbing program ini.');

        $validated = $request->validate([
            'penguji_1_id' => ['nullable', 'exists:users,id'],
            'penguji_2_id' => ['nullable', 'exists:users,id'],
        ]);

        // Normalisasi string kosong (dari form) menjadi null.
        $penguji1 = !empty($validated['penguji_1_id']) ? $validated['penguji_1_id'] : null;
        $penguji2 = !empty($validated['penguji_2_id']) ? $validated['penguji_2_id'] : null;

        // Cegah dosen yang sama dipakai di dua peran penguji, atau sebagai
        // pembimbing sekaligus penguji dalam program yang sama.
        if ($penguji1 && $penguji2 && (int) $penguji1 === (int) $penguji2) {
            abort(422, 'Satu dosen tidak boleh dipakai di lebih dari satu peran penguji.');
        }

        $dipakaiPembimbing = array_values(array_filter(array_unique([
            $mahasiswaTa->pembimbing_1_id,
            $mahasiswaTa->pembimbing_2_id,
        ])));
        foreach (array_filter([$penguji1, $penguji2]) as $id) {
            abort_if(in_array($id, $dipakaiPembimbing, true), 422, 'Dosen tidak boleh menjadi pembimbing sekaligus penguji dalam program yang sama.');
        }

        // Pastikan dosen yang dipilih benar-benar ber-role dosen.
        $pengujiIds = array_values(array_filter([$penguji1, $penguji2]));
        if ($pengujiIds) {
            $validDosen = User::role('dosen')->whereIn('id', $pengujiIds)->count();
            abort_if($validDosen !== count($pengujiIds), 422, 'Penguji yang dipilih tidak valid (bukan dosen).');
        }

        $old1 = $mahasiswaTa->penguji1?->name;
        $old2 = $mahasiswaTa->penguji2?->name;

        $mahasiswaTa->update([
            'penguji_1_id' => $penguji1,
            'penguji_2_id' => $penguji2,
        ]);
        $mahasiswaTa->load(['penguji1', 'penguji2']);

        // Audit log.
        Log::channel('audit')->info('Penguji diubah oleh pembimbing', [
            'mahasiswa_ta_id' => $mahasiswaTa->id,
            'jenis' => $mahasiswaTa->jenis,
            'mahasiswa' => $mahasiswaTa->mahasiswa?->name,
            'oleh' => $user->name.' ('.$user->id.')',
            'penguji_1' => ($old1 ?: '—').' → '.($mahasiswaTa->penguji1?->name ?? '—'),
            'penguji_2' => ($old2 ?: '—').' → '.($mahasiswaTa->penguji2?->name ?? '—'),
            'waktu' => now()->toDateTimeString(),
        ]);

        // Notifikasi mahasiswa.
        if ($mahasiswa = $mahasiswaTa->mahasiswa) {
            $this->bestEffort(fn () => $mahasiswa->notify(new \App\Notifications\ActivityNotification(
                "Dosen penguji untuk program ".$mahasiswaTa->jenisLabel()." Anda telah diperbarui oleh pembimbing.",
                route('profile.profil-akademik'),
                'Penguji Diperbarui',
            )));
        }

        return back()->with('success', 'Dosen penguji berhasil diperbarui.');
    }
}
