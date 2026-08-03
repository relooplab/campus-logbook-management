<?php

namespace App\Policies;

use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\User;

class LogbookEntryPolicy
{
    /**
     * Pembimbing resolve (priority sesuai spesifikasi).
     */
    private function pembimbingIds(?MahasiswaTa $ta, LogbookEntry $entry): array
    {
        $ids = [];
        if ($ta) {
            if ($ta->pembimbing_1_id) {
                $ids[] = $ta->pembimbing_1_id;
            }
            if ($ta->pembimbing_2_id) {
                $ids[] = $ta->pembimbing_2_id;
            }
        }
        if ($entry->dosen_id) {
            $ids[] = $entry->dosen_id;
        } elseif ($entry->parent_entry_id && $entry->parentEntry?->dosen_id) {
            $ids[] = $entry->parentEntry->dosen_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Mahasiswa pemilik TA.
     */
    public function owner(User $user, LogbookEntry $entry): bool
    {
        return $entry->mahasiswaTa?->user_id === $user->id;
    }

    public function create(User $user, ?MahasiswaTa $ta = null): bool
    {
        if (!$user->isMahasiswa()) {
            return false;
        }

        return $ta
            ? $ta->user_id === $user->id
            : $user->mahasiswaTa()->exists();
    }

    public function view(User $user, LogbookEntry $entry): bool
    {
        if ($this->owner($user, $entry)) {
            return true;
        }

        return in_array($user->id, $this->pembimbingIds($entry->mahasiswaTa, $entry), true);
    }

    public function update(User $user, LogbookEntry $entry): bool
    {
        return $this->owner($user, $entry) && $entry->isEditable();
    }

    public function submit(User $user, LogbookEntry $entry): bool
    {
        return $this->owner($user, $entry) && $entry->isEditable();
    }

    /**
     * Dosen yang merupakan pembimbing berhak mereview.
     */
    public function review(User $user, LogbookEntry $entry): bool
    {
        return $user->isDosen()
            && $entry->status === LogbookEntry::STATUS_SUBMITTED
            && in_array($user->id, $this->pembimbingIds($entry->mahasiswaTa, $entry), true);
    }

    public function delete(User $user, LogbookEntry $entry): bool
    {
        return $this->owner($user, $entry);
    }
}
