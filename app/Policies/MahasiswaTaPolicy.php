<?php

namespace App\Policies;

use App\Models\MahasiswaTa;
use App\Models\User;

class MahasiswaTaPolicy
{
    /**
     * Akses workspace: pemilik TA, dosen pembimbing (P1/P2), atau admin.
     */
    public function viewWorkspace(User $user, MahasiswaTa $mahasiswaTa): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->id === $mahasiswaTa->user_id) {
            return true;
        }

        // Dosen pembimbing ATAU penguji dapat melihat workspace.
        return $user->isDosen()
            && ($mahasiswaTa->isPembimbing($user) || $mahasiswaTa->isPenguji($user));
    }
}
