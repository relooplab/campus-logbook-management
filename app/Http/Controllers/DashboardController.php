<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\SeminarSubmission;
use App\Models\User;
use App\Services\MahasiswaDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private MahasiswaDashboardService $dashboardService)
    {
    }

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $this->adminDashboard($user);
        }

        if ($user->isDosen()) {
            return $this->dosenDashboard($user);
        }

        return $this->mahasiswaDashboard($user);
    }

    /**
     * Tutup (dismiss) kartu info institusi di dashboard dosen.
     */
    public function dismissInstansi(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isDosen(), 403, 'Hanya dosen.');

        session(['dosen_instansi_dismissed' => true]);

        return back();
    }

    private function adminDashboard(User $user): View
    {
        $stats = [
            'mahasiswa' => User::role('mahasiswa')->count(),
            'dosen' => User::role('dosen')->count(),
            'ta' => MahasiswaTa::count(),
            'menunggu_review' => LogbookEntry::where('status', LogbookEntry::STATUS_SUBMITTED)->count(),
        ];

        $tas = MahasiswaTa::with(['mahasiswa', 'pembimbing1', 'pembimbing2'])
            ->withCount('entries')
            ->latest()
            ->take(10)
            ->get();

        return view('dashboard.admin', compact('stats', 'tas'));
    }

    private function dosenDashboard(User $user): View
    {
        // TA where the dosen is pembimbing 1/2 atau penguji 1/2.
        $tas = MahasiswaTa::where(fn ($q) => $q->where('pembimbing_1_id', $user->id)
            ->orWhere('pembimbing_2_id', $user->id)
            ->orWhere('penguji_1_id', $user->id)
            ->orWhere('penguji_2_id', $user->id))
            ->with(['mahasiswa', 'pembimbing1', 'pembimbing2', 'penguji1', 'penguji2'])
            ->latest()
            ->get();

        // Antrean review hanya untuk TA yang benar-benar dibimbing (bukan diuji).
        $taIds = $tas->pluck('id');
        $reviewTaIds = MahasiswaTa::where('pembimbing_1_id', $user->id)
            ->orWhere('pembimbing_2_id', $user->id)
            ->pluck('id');
        $queue = LogbookEntry::where(function ($query) use ($reviewTaIds, $user) {
                $query->whereIn('mahasiswa_ta_id', $reviewTaIds)
                    ->orWhere('dosen_id', $user->id);
            })
            ->where('status', LogbookEntry::STATUS_SUBMITTED)
            ->with(['mahasiswaTa.mahasiswa'])
            ->latest()
            ->get();

        // Statistik & progres per mahasiswa bimbingan.
        $entries = LogbookEntry::whereIn('mahasiswa_ta_id', $taIds)->get();
        $perTa = $tas->map(function ($ta) use ($entries) {
            $e = $entries->where('mahasiswa_ta_id', $ta->id);
            $approved = $e->where('jenis', LogbookEntry::JENIS_LOGBOOK)->where('status', LogbookEntry::STATUS_APPROVED)->count();
            $total = $e->count();
            $target = $ta->target_sesi ?? 7;
            $percent = $target > 0 ? (int) round($approved / $target * 100) : 0;

            return [
                'ta' => $ta,
                'approved' => $approved,
                'total' => $total,
                'target' => $target,
                'percent' => $percent,
                'menunggu' => $e->where('status', LogbookEntry::STATUS_SUBMITTED)->count(),
                'regularity' => $ta->regularity_status,
                'tooltip' => $ta->regularity_tooltip,
                'warned' => $ta->wasWarnedInactive(),
            ];
        })
        ->sortBy(fn ($r) => ['red' => 0, 'yellow' => 1, 'green' => 2][$r['regularity']] ?? 3)
        ->values();

        // Summary counter health indicator.
        $healthCount = [
            'green' => $perTa->where('regularity', 'green')->count(),
            'yellow' => $perTa->where('regularity', 'yellow')->count(),
            'red' => $perTa->where('regularity', 'red')->count(),
        ];

        // Kartu statistik bimbingan & pengujian.
        $stats = [
            'total_bimbingan' => MahasiswaTa::bimbinganOleh($user)->count(),
            'sedang_progres' => MahasiswaTa::bimbinganOleh($user)->aktif()->count(),
            'tamat' => MahasiswaTa::bimbinganOleh($user)->tamat()->count(),
            'diuji' => \App\Models\Sidang::where('penguji_id', $user->id)->count(),
            'menunggu_review' => LogbookEntry::where('status', LogbookEntry::STATUS_SUBMITTED)
                ->whereIn('mahasiswa_ta_id', $taIds)->count(),
        ];

        // Ringkasan aksi untuk dosen: permintaan attachment pending + mahasiswa perlu perhatian.
        $pendingRegistrations = MahasiswaTa::where('status_ta', MahasiswaTa::STATUS_PENDING_APPROVAL)
            ->where(fn ($q) => $q->where('pembimbing_1_id', $user->id)
                ->orWhere('pembimbing_2_id', $user->id)
                ->orWhere('penguji_1_id', $user->id)
                ->orWhere('penguji_2_id', $user->id))
            ->count();
        $needsAttention = $perTa->whereIn('regularity', ['yellow', 'red'])->count();

        // Informasi institusi & grup untuk kartu dashboard.
        $university = $user->primaryUniversity();
        // Instansi dosen dianggap lengkap jika NIDN + hierarki direktori terisi.
        $instansiComplete = false;
        if ($user->nidn && $university && $university->pivot?->faculty_id
            && $university->pivot?->department_id && $university->pivot?->study_program_id) {
            $instansiComplete = true;
        }

        $groupCount = Group::whereHas('memberships', fn ($q) => $q->where('user_id', $user->id)->where('status', 'approved'))
            ->count();

        // ---- Agenda terdekat: jadwal seminar/sidang mahasiswa bimbingan/pengujian ----
        $agendaTerdekat = SeminarSubmission::where('status', SeminarSubmission::STATUS_SUBMITTED)
            ->where('tanggal', '>=', now()->toDateString())
            ->whereIn('mahasiswa_ta_id', $taIds)
            ->with(['mahasiswaTa.mahasiswa'])
            ->orderBy('tanggal')
            ->orderBy('waktu')
            ->limit(10)
            ->get();

        // ---- Submission terbaru mahasiswa bimbingan/pengujian ----
        $submissions = SeminarSubmission::whereIn('mahasiswa_ta_id', $taIds)
            ->with(['mahasiswaTa.mahasiswa'])
            ->latest()
            ->get();

        return view('dashboard.dosen', compact(
            'tas', 'queue', 'perTa', 'healthCount', 'stats',
            'pendingRegistrations', 'needsAttention',
            'university', 'instansiComplete', 'groupCount', 'agendaTerdekat', 'submissions'
        ));
    }

    /**
     * List mahasiswa bimbingan dosen (filter status).
     */
    public function dosenMahasiswaList(Request $request): View
    {
        $user = $request->user();
        $status = $request->query('status', 'all');

        $query = MahasiswaTa::bimbinganOleh($user)
            ->with(['mahasiswa', 'pembimbing1', 'pembimbing2'])
            ->when(in_array($status, [MahasiswaTa::STATUS_AKTIF, MahasiswaTa::STATUS_TAMAT]), function ($q) use ($status) {
                $q->where('status_ta', $status);
            });

        // Urutkan berdasarkan urgensi: merah (butuh perhatian) di atas, lalu kuning, lalu hijau.
        $priority = ['red' => 0, 'yellow' => 1, 'green' => 2];

        $list = $query->latest()->get()
            ->map(fn ($ta) => [
                'ta' => $ta,
                'regularity' => $ta->regularity_status,
                'tooltip' => $ta->regularity_tooltip,
            ])
            ->sortBy(fn ($item) => $priority[$item['regularity']] ?? 3)
            ->values();

        return view('dashboard.dosen-mahasiswa-list', compact('list', 'status', 'user'));
    }

    /**
     * Halaman "Mahasiswa Saya" dosen — daftar mahasiswa yang dibimbing (pembimbing 1/2)
     * atau diuji (penguji 1/2), dikelompokkan, baik TA maupun KP. Setiap entri
     * disertai label peran dosen (bisa lebih dari satu jika dosen merangkap).
     * Agar tidak duplikat, mahasiswa yang dosen BIMBING tampil di seksi Dibimbing
     * saja (peran penguji ditampilkan sebagai chip tambahan), bukan muncul di
     * kedua seksi.
     */
    public function mahasiswaSaya(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isDosen(), 403, 'Halaman ini khusus dosen.');

        $with = ['mahasiswa', 'pembimbing1', 'pembimbing2', 'penguji1', 'penguji2'];

        // Ambil semua TA di mana dosen terkait (bimbing/uji), lalu anotasi peran.
        $all = MahasiswaTa::where(function ($q) use ($user) {
                $q->where('pembimbing_1_id', $user->id)
                    ->orWhere('pembimbing_2_id', $user->id)
                    ->orWhere('penguji_1_id', $user->id)
                    ->orWhere('penguji_2_id', $user->id);
            })
            ->with($with)
            ->latest()
            ->get()
            ->map(function ($ta) use ($user) {
                $roles = [];
                if ($ta->pembimbing_1_id === $user->id) $roles[] = 'Pembimbing 1';
                if ($ta->pembimbing_2_id === $user->id) $roles[] = 'Pembimbing 2';
                if ($ta->penguji_1_id === $user->id) $roles[] = 'Penguji 1';
                if ($ta->penguji_2_id === $user->id) $roles[] = 'Penguji 2';
                $ta->my_roles = $roles;
                return $ta;
            });

        // "Dibimbing" = dosen adalah pembimbing (peran penguji ditampilkan sebagai chip).
        $dibimbing = $all->filter(fn ($ta) => in_array('Pembimbing 1', $ta->my_roles, true)
            || in_array('Pembimbing 2', $ta->my_roles, true))->values();

        // "Diuji" = dosen HANYA penguji (bukan pembimbing) — agar tidak duplikat.
        $diuji = $all->filter(fn ($ta) => !in_array('Pembimbing 1', $ta->my_roles, true)
            && !in_array('Pembimbing 2', $ta->my_roles, true))->values();

        return view('dashboard.dosen-mahasiswa-saya', compact('dibimbing', 'diuji', 'user'));
    }

    /**
     * Tutup (dismiss) card "Lanjut ke TA" di dashboard mahasiswa untuk sesi ini.
     * Link permanen tetap tersedia di halaman Profil.
     */
    public function dismissLanjutTa(Request $request): RedirectResponse
    {
        $request->session()->put('lanjut_ta_dismissed', true);
        return back();
    }

    /**
     * Riwayat menguji dosen.
     */
    public function dosenSidangList(Request $request): View
    {
        $user = $request->user();

        $sidangs = \App\Models\Sidang::where('penguji_id', $user->id)
            ->with(['mahasiswaTa.mahasiswa'])
            ->orderByDesc('tanggal')
            ->get();

        return view('dashboard.dosen-sidang-list', compact('sidangs', 'user'));
    }

    private function mahasiswaDashboard(User $user): View
    {
        // Program yang ditampilkan: default program aktif; bisa dipilih via ?program=kp|ta.
        $programs = $user->allPrograms()->with(['pembimbing1', 'pembimbing2', 'penguji1', 'penguji2', 'members'])->get();
        $activeProgram = $user->programAktif;

        $requested = request()->query('program');
        $program = $requested === 'kp' || $requested === 'ta'
            ? $programs->firstWhere('jenis', $requested)
            : null;

        $ta = $program ?: ($activeProgram ?: $programs->first());

        $entries = $ta
            ? $ta->entries()->with('comments')->latest()->get()
            : collect();

        $approved = $entries->where('jenis', LogbookEntry::JENIS_LOGBOOK)->where('status', LogbookEntry::STATUS_APPROVED)->count();
        $target = $ta?->target_sesi ?? 7;
        $progressPercent = $target > 0 ? (int) round($approved / $target * 100) : 0;

        // ---- Milestone fase ----
        $faseKeys = $ta && $ta->isKp() ? array_keys(\App\Models\MahasiswaTa::FASES_KP) : array_keys(\App\Models\MahasiswaTa::FASES);
        $faseIndex = $ta ? array_search($ta->fase, $faseKeys, true) : 0;
        if ($faseIndex === false) $faseIndex = 0;
        $faseLabels = $ta ? app(\App\Services\ProgramNamingService::class)->faseLabels($ta) : [];

        // ---- Achievement (unlocked + total) - hanya untuk TA ----
        $unlockedAchievements = $ta && $ta->isTa() ? $user->achievements()->get() : collect();
        $unlockedCodes = $unlockedAchievements->pluck('code')->map(fn ($c) => (string) $c);
        $totalAchievements = $ta && $ta->isTa() ? \App\Models\Achievement::count() : 0;

        // ---- Logbook harian (hanya KP) ----
        $logbookHarian = $ta && $ta->isKp()
            ? $ta->logbookHarian()->orderByDesc('tanggal')->get()
            : collect();

        // ---- Statistik & streak ----
        $stats = $this->dashboardService->buildStats($ta, $entries);

        // ---- Timeline aktivitas ----
        $timeline = $this->dashboardService->buildTimeline($ta, $entries);

        // ---- Heatmap data (aktivitas per hari, 12 bulan) ----
        $heatmap = $this->dashboardService->buildHeatmap($ta, $entries);

        // ---- Health indicator (self-awareness) ----
        $regularity = $ta ? $ta->regularity_status : 'red';
        $regularityTooltip = $ta ? $ta->regularity_tooltip : 'Belum ada data';

        // ---- Pengumuman belum dibaca (banner) ----
        $unreadAnnouncements = $user->announcements()
            ->with('sender')
            ->wherePivotNull('read_at')
            ->orderByDesc('created_at')
            ->get();

        // ---- Ringkasan "Aksi Saya" untuk mahasiswa ----
        $draftCount = $entries->where('status', LogbookEntry::STATUS_DRAFT)->count();
        $revisiCount = $entries->where('status', LogbookEntry::STATUS_REVISI)->count();
        $unresolvedActionItems = $ta
            ? \App\Models\ActionItem::whereHas('entry', fn ($q) => $q->where('mahasiswa_ta_id', $ta->id))
                ->where('is_done', false)
                ->count()
            : 0;

        // Informasi universitas untuk kartu dashboard mahasiswa.
        $university = $user->isMahasiswa()
            ? ($user->primaryUniversity() ?? $ta?->pembimbing1?->primaryUniversity())
            : $user->primaryUniversity();

        $nilai = $ta?->finalization?->nilai;

        // ---- Ringkasan hasil sidang/seminar (untuk dilihat mahasiswa sendiri) ----
        $sidangs = $ta
            ? $ta->sidangs()->orderByDesc('tanggal')->get()
            : collect();

        // ---- Agenda terdekat (jadwal seminar/sidang yang akan datang) ----
        $agendaTerdekat = $ta
            ? SeminarSubmission::where('mahasiswa_ta_id', $ta->id)
                ->where('status', SeminarSubmission::STATUS_SUBMITTED)
                ->where('tanggal', '>=', now()->toDateString())
                ->orderBy('tanggal')
                ->orderBy('waktu')
                ->limit(10)
                ->get()
            : collect();

        // ---- Submission terbaru untuk status tombol ----
        $seminarSubmission = $ta
            ? $ta->seminarSubmissions()->latest()->first()
            : null;

        // ---- Status mahasiswa (aktif/verified) untuk banner ----
        $mahasiswaStatus = $user->registration_status;
        $pendingApproval = $ta && $ta->status_ta === \App\Models\MahasiswaTa::STATUS_PENDING_APPROVAL;
        // $ta bisa jatuh ke program yang sudah ditolak (allPrograms() tidak difilter status
        // saat programAktif() kosong) — tandai agar mahasiswa tetap diarahkan pilih dosen lagi.
        $rejectedProgram = $ta && $ta->status_ta === \App\Models\MahasiswaTa::STATUS_DITOLAK;

        // Profil dianggap belum lengkap jika NIM (identifier), WhatsApp, atau afiliasi
        // perguruan tinggi (sampai prodi) belum diisi mahasiswa.
        $profileIncomplete = blank($user->identifier) || blank($user->whatsapp) || ! $user->primaryUniversity()?->pivot?->study_program_id;

        return view('dashboard.mahasiswa', compact(
            'programs', 'activeProgram', 'ta', 'entries', 'approved', 'target', 'progressPercent',
            'faseKeys', 'faseIndex', 'faseLabels',
            'unlockedAchievements', 'unlockedCodes', 'totalAchievements',
            'logbookHarian',
            'stats', 'timeline', 'heatmap', 'regularity', 'regularityTooltip',
            'unreadAnnouncements',
            'draftCount', 'revisiCount', 'unresolvedActionItems',
            'university', 'nilai', 'sidangs', 'agendaTerdekat', 'seminarSubmission',
            'mahasiswaStatus', 'pendingApproval', 'rejectedProgram', 'profileIncomplete'
        ));
    }

}
