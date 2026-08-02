<?php

namespace App\Observers;

use App\Models\LogbookEntry;
use Illuminate\Support\Facades\Cache;

class LogbookEntryObserver
{
    /**
     * Invalidate cache regularity saat entry dibuat/status berubah,
     * agar health indicator selalu segar.
     */
    private function forgetCache(LogbookEntry $entry): void
    {
        if ($taId = $entry->mahasiswa_ta_id) {
            Cache::forget("regularity:{$taId}");
        }
    }

    public function created(LogbookEntry $entry): void
    {
        $this->forgetCache($entry);
    }

    public function updated(LogbookEntry $entry): void
    {
        $this->forgetCache($entry);
    }
}
