<?php

namespace App\Services;

use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;

/**
 * Logika bersama untuk data dashboard mahasiswa (stats, timeline, heatmap).
 * Dipakai oleh DashboardController (mahasiswa) & MahasiswaTaController (profil dosen).
 */
class MahasiswaDashboardService
{
    public function buildStats(?MahasiswaTa $ta, $entries): array
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
        // Hanya entry yang benar-benar sudah diresubmit (submitted_at > reviewed_at)
        // yang dihitung; hindari nilai negatif dari entry masih berstatus REVISI.
        $responses = $entries
            ->whereNotNull('feedback_dosen')
            ->whereNotNull('submitted_at')
            ->whereNotNull('reviewed_at')
            ->filter(fn ($e) => $e->submitted_at->greaterThan($e->reviewed_at));
        $avgResponse = $responses->isNotEmpty()
            ? (int) round(max(0, $responses->avg(fn ($e) => $e->submitted_at->diffInDays($e->reviewed_at))))
            : null;

        // Streak konsistensi (minggu beruntun dengan >= 1 bimbingan).
        $streak = $this->weekStreak($ta, $entries);

        return compact('revisi', 'submitted', 'draft', 'ratioRevisi', 'avgWait', 'avgResponse', 'streak');
    }

    public function weekStreak(?MahasiswaTa $ta, $entries): int
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

    public function buildTimeline(?MahasiswaTa $ta, $entries): array
    {
        $items = [];

        foreach ($entries->take(8) as $e) {
            $label = $e->jenis === LogbookEntry::JENIS_REVISI ? 'Revisi' : 'Entri #'.$e->sesi_ke.' "'.($e->topik ?: 'Logbook').'"';
            $items[] = [
                'date' => $e->created_at->format('d M'),
                'ts' => $e->created_at->timestamp,
                'label' => $label,
                'status' => $e->status,
                'type' => 'entry',
                'url' => route('logbook.show', $e),
            ];
            // Tambah sub-item untuk komentar PDF pada entry tersebut.
            $commentCount = $e->comments->count();
            if ($commentCount > 0) {
                $items[] = [
                    'date' => $e->created_at->format('d M'),
                    'ts' => $e->created_at->timestamp,
                    'label' => "Komentar pada draft ({$commentCount} area)",
                    'status' => 'comment',
                    'type' => 'comment',
                    'url' => route('logbook.show', $e),
                ];
            }
        }

        // Item workspace (upload file baru).
        if ($ta) {
            foreach ($ta->workspaceFiles()->with('uploader')->latest()->take(3)->get() as $wf) {
                $items[] = [
                    'date' => $wf->created_at->format('d M'),
                    'ts' => $wf->created_at->timestamp,
                    'label' => '📁 '.($wf->bab ? $wf->bab.' — ' : '').'File diunggah oleh '.($wf->uploader?->name ?? ''),
                    'status' => 'comment',
                    'type' => 'workspace',
                ];
            }
        }

        // Item "masa depan" sesi berikutnya (selalu di paling bawah).
        $next = $ta ? $ta->entries()->where('jenis', LogbookEntry::JENIS_LOGBOOK)->count() + 1 : 1;
        $last = $entries->first();
        $daysSince = $last ? (int) $last->created_at->diffInDays(now()) : null;
        $items[] = [
            'date' => 'Akan datang',
            'ts' => PHP_INT_MAX, // selalu di paling bawah setelah sort
            'label' => "Sesi {$next} — belum ada bimbingan".($daysSince ? " ({$daysSince} hari sejak sesi terakhir)" : ''),
            'status' => 'future',
            'type' => 'future',
        ];

        // Urutkan kronologis menurun (terbaru dulu), item "Akan datang" di paling bawah.
        usort($items, fn ($a, $b) => $b['ts'] <=> $a['ts']);

        return $items;
    }

    public function buildHeatmap(?MahasiswaTa $ta, $entries): array
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