<?php

namespace App\Http\Controllers;

use App\Models\FeedbackTemplate;
use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuickReviewController extends Controller
{
    /**
     * Quick review: tampilkan entry antrean berikutnya + tombol Approve & Next.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Antrean review untuk dosen ini.
        $taIds = MahasiswaTa::where('pembimbing_1_id', $user->id)
            ->orWhere('pembimbing_2_id', $user->id)
            ->pluck('id');

        $queue = LogbookEntry::where('status', LogbookEntry::STATUS_SUBMITTED)
            ->where(function ($query) use ($taIds, $user) {
                $query->whereIn('mahasiswa_ta_id', $taIds)
                    ->orWhere('dosen_id', $user->id);
            });
        $queueCount = (clone $queue)->count();
        $entry = $queue
            ->with(['mahasiswaTa.mahasiswa', 'comments.user', 'parentEntry.comments.user'])
            ->oldest('submitted_at')
            ->first();

        if ($entry) {
            $this->authorize('review', $entry);
        }

        $templates = FeedbackTemplate::where('user_id', $user->id)->get();
        $lastFeedback = $entry ? $this->lastFeedbackForStudent($entry) : null;

        // Feedback draft dari localStorage (diset tombol "Jadikan Feedback").
        $feedbackDraft = $request->session()->pull('feedback_draft');

        return view('logbook.quick-review', compact('entry', 'templates', 'lastFeedback', 'feedbackDraft', 'queueCount'));
    }

    /**
     * Approve & next: setujui entry lalu redirect ke antrean berikutnya.
     */
    public function approveNext(Request $request, LogbookEntry $logbook): RedirectResponse
    {
        $this->authorize('review', $logbook);

        // Hanya program aktif yang bisa di-review.
        abort_unless($logbook->mahasiswaTa?->status_ta === \App\Models\MahasiswaTa::STATUS_AKTIF, 403, 'Program belum aktif.');

        $logbook->update([
            'status' => LogbookEntry::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
        $this->resolveCommentsOnApproval($logbook);

        $this->bestEffort(fn () => \App\Events\EntryStatusChanged::dispatch($logbook, 'Entri Anda telah disetujui.'));
        $logbook->notifyParties('Entri '.($logbook->jenis === 'revisi' ? 'revisi' : 'logbook sesi '.$logbook->sesi_ke).' telah disetujui.', route('logbook.show', $logbook), 'Entri Disetujui');
        if ($owner = $logbook->mahasiswaTa?->mahasiswa) {
            app(\App\Services\AchievementService::class)->evaluateForUser($owner);
        }

        return redirect()->route('quick-review.index')
            ->with('success', 'Entri disetujui. Lanjut ke berikutnya.');
    }

    /**
     * Revisi & next: simpan feedback (dengan template/build dari komentar) lalu next.
     */
    public function revisiNext(Request $request, LogbookEntry $logbook): RedirectResponse
    {
        $this->authorize('review', $logbook);

        // Hanya program aktif yang bisa di-review.
        abort_unless($logbook->mahasiswaTa?->status_ta === \App\Models\MahasiswaTa::STATUS_AKTIF, 403, 'Program belum aktif.');

        $validated = $request->validate([
            'feedback_dosen' => ['required', 'string', 'min:20'],
        ]);

        $logbook->update([
            'status' => LogbookEntry::STATUS_REVISI,
            'feedback_dosen' => $validated['feedback_dosen'],
            'reviewed_at' => now(),
        ]);

        $this->bestEffort(fn () => \App\Events\EntryStatusChanged::dispatch($logbook, 'Entri Anda diminta revisi.'));
        $logbook->notifyParties('Entri Anda diminta revisi: '.$validated['feedback_dosen'], route('logbook.show', $logbook), 'Permintaan Revisi');

        return redirect()->route('quick-review.index')
            ->with('success', 'Entri dikembalikan untuk revisi. Lanjut ke berikutnya.');
    }

    /**
     * Simpan template feedback (snippet).
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:100'],
            'body' => ['required', 'string'],
        ]);

        $tpl = FeedbackTemplate::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'] ?: null,
            'body' => $validated['body'],
        ]);

        return response()->json($tpl, 201);
    }

    public function destroyTemplate(Request $request, FeedbackTemplate $template): JsonResponse
    {
        if ($template->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403);
        }
        $template->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Compile feedback otomatis dari komentar PDF yang belum resolve,
     * lalu simpan ke session untuk dipakai di quick review.
     */
    public function buildFeedbackFromComments(Request $request, LogbookEntry $logbook): JsonResponse
    {
        $this->authorize('review', $logbook);

        $entryIds = [$logbook->id];
        $cursor = $logbook->parentEntry;
        while ($cursor) {
            $entryIds[] = $cursor->id;
            $cursor = $cursor->parentEntry;
        }

        $comments = \App\Models\PdfComment::whereIn('logbook_entry_id', $entryIds)
            ->where('resolution_status', \App\Models\PdfComment::STATUS_OPEN)
            ->orderBy('page_number')
            ->get();

        if ($comments->isEmpty()) {
            return response()->json(['feedback' => '']);
        }

        $lines = $comments->map(function ($c, $i) use ($logbook) {
            $source = $c->logbook_entry_id === $logbook->id
                ? 'Ronde ini'
                : 'Ronde sebelumnya, entri #'.$c->logbook_entry_id;

            return ($i + 1).'. ('.$source.', Hal. '.$c->page_number.') '.$c->comment;
        });
        $feedback = $lines->implode("\n");

        // Simpan ke session untuk dipakai di quick review.
        $request->session()->put('feedback_draft', $feedback);

        return response()->json(['feedback' => $feedback]);
    }

    private function lastFeedbackForStudent(LogbookEntry $entry): ?string
    {
        $taId = $entry->mahasiswa_ta_id;

        return LogbookEntry::where('mahasiswa_ta_id', $taId)
            ->whereNotNull('feedback_dosen')
            ->orderByDesc('id')
            ->value('feedback_dosen');
    }

    private function resolveCommentsOnApproval(LogbookEntry $logbook): void
    {
        $entries = collect([$logbook, $logbook->parentEntry])->filter();

        foreach ($entries as $entry) {
            $entry->comments()
                ->where('resolution_status', '!=', \App\Models\PdfComment::STATUS_RESOLVED)
                ->get()
                ->each(function ($comment) {
                    $comment->setResolutionStatus(\App\Models\PdfComment::STATUS_RESOLVED);
                    $comment->save();
                });
        }
    }
}
