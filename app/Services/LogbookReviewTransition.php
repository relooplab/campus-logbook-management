<?php

namespace App\Services;

use App\Models\LogbookEntry;

class LogbookReviewTransition
{
    public function apply(LogbookEntry $entry, string $status, array $attributes = []): void
    {
        abort_unless($this->tryApply($entry, $status, $attributes), 403, 'Entri sudah diproses reviewer lain. Muat ulang halaman.');
    }

    public function tryApply(LogbookEntry $entry, string $status, array $attributes = []): bool
    {
        // CAS: keputusan lain yang menang lebih dulu tak boleh ditimpa model stale.
        $updated = LogbookEntry::whereKey($entry->id)
            ->where('status', LogbookEntry::STATUS_SUBMITTED)
            ->update(array_merge($attributes, ['status' => $status]));
        if ($updated === 1) {
            $entry->refresh();
            return true;
        }

        return false;
    }
}
