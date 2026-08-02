<?php

namespace App\Http\Controllers;

use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
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
        $tas = MahasiswaTa::where('pembimbing_1_id', $user->id)
            ->orWhere('pembimbing_2_id', $user->id)
            ->orWhere('penguji_1_id', $user->id)
            ->orWhere('penguji_2_id', $user->id)
            ->with(['mahasiswa', 'pembimbing1', 'pembimbing2', 'penguji1', 'penguji2'])
            ->latest()
            ->get();

        // Antrean review: submitted entries belonging to those TAs.
        $taIds = $tas->pluck('id');
        $queue = LogbookEntry::whereIn('mahasiswa_ta_id', $taIds)
            ->where('status', LogbookEntry::STATUS_SUBMITTED)
            ->with(['mahasiswaTa.mahasiswa'])
            ->latest()
            ->get();

        // Statistik & progres per mahasiswa bimbingan.
        $entries = LogbookEntry::whereIn('mahasiswa_ta_id', $taIds)->get();
        $perTa = $tas->map(function ($ta) use ($entries) {
            $e = $entries->where('mahasiswa_ta_id', $ta->id);
            $approved = $e->where('status', LogbookEntry::STATUS_APPROVED)->count();
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

        return view('dashboard.dosen', compact('tas', 'queue', 'perTa', 'healthCount', 'stats'));
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

        $list = $query->latest()->get()->map(fn ($ta) => [
            'ta' => $ta,
            'regularity' => $ta->regularity_status,
            'tooltip' => $ta->regularity_tooltip,
        ]);

        return view('dashboard.dosen-mahasiswa-list', compact('list', 'status', 'user'));
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
        $ta = $user->mahasiswaTa()->with(['pembimbing1', 'pembimbing2', 'penguji1', 'penguji2'])->first();

        $entries = $ta
            ? $ta->entries()->with('comments')->latest()->get()
            : collect();

        $approved = $entries->where('status', LogbookEntry::STATUS_APPROVED)->count();
        $target = $ta?->target_sesi ?? 7;
        $progressPercent = $target > 0 ? (int) round($approved / $target * 100) : 0;

        // ---- Milestone fase ----
        $faseKeys = array_keys(\App\Models\MahasiswaTa::FASES);
        $faseIndex = $ta ? array_search($ta->fase, $faseKeys, true) : 0;
        if ($faseIndex === false) $faseIndex = 0;

        // ---- Achievement (unlocked + total) ----
        $unlockedAchievements = $ta ? $user->achievements()->get() : collect();
        $unlockedCodes = $unlockedAchievements->pluck('code')->map(fn ($c) => (string) $c);
        $totalAchievements = \App\Models\Achievement::count();

        // ---- Statistik & streak ----
        $stats = $this->mahasiswaStats($ta, $entries);

        // ---- Timeline aktivitas ----
        $timeline = $this->buildTimeline($ta, $entries);

        // ---- Heatmap data (aktivitas per hari, 12 bulan) ----
        $heatmap = $this->buildHeatmap($ta, $entries);

        // ---- Health indicator (self-awareness) ----
        $regularity = $ta ? $ta->regularity_status : 'red';
        $regularityTooltip = $ta ? $ta->regularity_tooltip : 'Belum ada data';

        // ---- Pengumuman belum dibaca (banner) ----
        $unreadAnnouncements = $user->announcements()
            ->with('sender')
            ->wherePivotNull('read_at')
            ->orderByDesc('created_at')
            ->get();

        return view('dashboard.mahasiswa', compact(
            'ta', 'entries', 'approved', 'target', 'progressPercent',
            'faseKeys', 'faseIndex',
            'unlockedAchievements', 'unlockedCodes', 'totalAchievements',
            'stats', 'timeline', 'heatmap', 'regularity', 'regularityTooltip',
            'unreadAnnouncements'
        ));
    }

    private function mahasiswaStats($ta, $entries): array
    {
        $revisi = $entries->where('status', LogbookEntry::STATUS_REVISI)->count();
        $submitted = $entries->where('status', LogbookEntry::STATUS_SUBMITTED);
        $draft = $entries->where('status', LogbookEntry::STATUS_DRAFT)->count();

        // Rasio revisi: entri yang pernah direvisi / total.
        $ratioRevisi = $entries->count() > 0
            ? (int) round($revisi / $entries->count() * 100)
            : 0;

        // Rata-rata hari menunggu review (submitted_at -> reviewed_at).
        $reviewed = $entries->whereNotNull('reviewed_at')->whereNotNull('submitted_at');
        $avgWait = $reviewed->isNotEmpty()
            ? (int) round($reviewed->avg(fn ($e) => $e->reviewed_at->diffInHours($e->submitted_at) / 24))
            : null;

        // Kecepatan merespons revisi (reviewed_at revisi -> submitted_at ulang).
        $responses = $entries->whereNotNull('feedback_dosen')->whereNotNull('submitted_at');
        $avgResponse = $responses->isNotEmpty()
            ? (int) round($responses->avg(fn ($e) => $e->submitted_at->diffInDays($e->reviewed_at)))
            : null;

        // Streak konsistensi (minggu beruntun dengan >= 1 bimbingan).
        $streak = $this->weekStreak($ta, $entries);

        return compact('revisi', 'submitted', 'draft', 'ratioRevisi', 'avgWait', 'avgResponse', 'streak');
    }

    private function weekStreak($ta, $entries): int
    {
        $dates = $entries->pluck('created_at')
            ->filter()
            ->map(fn ($d) => $d->startOfWeek()->toDateString())
            ->unique()
            ->sortDesc()
            ->values();

        if ($dates->isEmpty()) return 0;

        $streak = 0;
        $current = now()->startOfWeek();
        foreach ($dates as $week) {
            $w = \Carbon\Carbon::parse($week);
            if ($w->eq($current) || $w->eq($current->copy()->subWeek())) {
                $streak++;
                $current = $w->copy()->subWeek();
            } else {
                break;
            }
        }
        return $streak;
    }

    private function buildTimeline($ta, $entries): array
    {
        $items = [];

        foreach ($entries->take(8) as $e) {
            $label = $e->jenis === LogbookEntry::JENIS_REVISI ? 'Revisi' : 'Entri #'.$e->sesi_ke.' "'.($e->topik ?: 'Logbook').'"';
            $items[] = [
                'date' => $e->created_at->format('d M'),
                'label' => $label,
                'status' => $e->status,
                'type' => 'entry',
            ];
            // Tambah sub-item untuk komentar PDF pada entry tersebut.
            $commentCount = $e->comments->count();
            if ($commentCount > 0) {
                $items[] = [
                    'date' => $e->created_at->format('d M'),
                    'label' => "Komentar pada draft ({$commentCount} area)",
                    'status' => 'comment',
                    'type' => 'comment',
                ];
            }
        }

        // Item workspace (upload file baru).
        if ($ta) {
            foreach ($ta->workspaceFiles()->with('uploader')->latest()->take(3)->get() as $wf) {
                $items[] = [
                    'date' => $wf->created_at->format('d M'),
                    'label' => '📁 '.($wf->bab ? $wf->bab.' — ' : '').'File diunggah oleh '.($wf->uploader?->name ?? ''),
                    'status' => 'comment',
                    'type' => 'workspace',
                ];
            }
        }

        // Item "masa depan" sesi berikutnya.
        $next = $ta ? $ta->entries()->where('jenis', LogbookEntry::JENIS_LOGBOOK)->count() + 1 : 1;
        $last = $entries->first();
        $daysSince = $last ? (int) $last->created_at->diffInDays(now()) : null;
        $items[] = [
            'date' => 'Akan datang',
            'label' => "Sesi {$next} — belum ada bimbingan".($daysSince ? " ({$daysSince} hari sejak sesi terakhir)" : ''),
            'status' => 'future',
            'type' => 'future',
        ];

        return $items;
    }

    private function buildHeatmap($ta, $entries): array
    {
        $counts = [];
        if ($ta) {
            $counts = $ta->entries()
                ->selectRaw("date(created_at) as d, count(*) as c")
                ->groupBy('d')
                ->pluck('c', 'd')
                ->map(fn ($c) => (int) $c)
                ->toArray();
        }

        // 7 kolom x ~52 minggu (12 bulan).
        $weeks = [];
        $end = now()->endOfWeek();
        $start = $end->copy()->subWeeks(51)->startOfWeek();

        $cursor = $start->copy();
        while ($cursor <= $end) {
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $d = $cursor->copy()->addDays($i);
                $key = $d->toDateString();
                $days[] = [
                    'date' => $key,
                    'count' => $counts[$key] ?? 0,
                ];
            }
            $weeks[] = $days;
            $cursor->addWeek();
        }

        return $weeks;
    }
}
