<?php

namespace App\Services;

use App\Events\EntryStatusChanged;
use App\Models\LogbookEntry;
use App\Models\User;
use App\Support\Audit;

class ArchiveLogbookReview
{
    public function __construct(private LogbookReviewTransition $transition)
    {
    }

    public function archive(LogbookEntry $entry, User $reviewer, ?string $reason): void
    {
        $archivedAt = now();
        $this->transition->apply($entry, LogbookEntry::STATUS_ARCHIVED, [
            'archive_reason' => $reason,
            'archived_by' => $reviewer->id,
            'archived_at' => $archivedAt,
            'reviewed_at' => $archivedAt,
        ]);

        Audit::log('Dosen arsipkan entri logbook', [
            'entry_id' => $entry->id,
            'archived_by' => $reviewer->id,
            'reason' => $reason,
        ]);
        try {
            EntryStatusChanged::dispatch($entry, 'Entri Anda diarsipkan oleh dosen.');
        } catch (\Throwable $e) {
            report($e);
        }
        $entry->notifyParties(
            'Entri '.($entry->jenis === LogbookEntry::JENIS_REVISI ? 'revisi' : 'logbook sesi '.$entry->sesi_ke).' diarsipkan oleh dosen.'.($reason ? ' Alasan: '.$reason : ''),
            route('logbook.show', $entry),
            'Entri Diarsipkan',
        );
    }
}
