<?php

namespace App\Policies;

use App\Models\MahasiswaTa;
use App\Models\User;

class MahasiswaTaPolicy
{
    /**
     * Akses workspace: pemilik TA, dosen pembimbing (P1/P2), dosen yang
     * punya hubungan langsung dengan pembimbing (grup/TA bersama), atau admin.
     */
    public function viewWorkspace(User $user, MahasiswaTa $mahasiswaTa): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($mahasiswaTa->isMember($user)) {
            return true;
        }

        // Dosen pembimbing ATAU penguji dapat melihat workspace.
        if ($user->isDosen() && ($mahasiswaTa->isPembimbing($user) || $mahasiswaTa->isPenguji($user))) {
            return true;
        }

        // Dosen lain yang punya hubungan langsung dengan pembimbing
        // (TA bersama atau grup yang sama) dapat melihat workspace.
        if ($user->isDosen()) {
            foreach ($mahasiswaTa->allDosenIds() as $dosenId) {
                if ($dosen = User::find($dosenId)) {
                    if ($user->hasDirectRelation($dosen)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
