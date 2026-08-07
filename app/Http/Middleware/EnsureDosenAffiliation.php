<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate onboarding dosen: dosen tidak dapat mengakses fitur lain sampai
 * afiliasi institusi (universitas › fakultas › departemen › prodi) terisi.
 * Hanya halaman afiliasi + logout yang tetap terbuka.
 */
class EnsureDosenAffiliation
{
    public function handle(Request $request, Closure $next): Response
    {
        // Gate onboarding hanya aktif di luar env testing (agar fixture test berjalan).
        if (! config('app.enforce_dosen_affiliation')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && $user->isDosen() && ! $this->hasAffiliation($user)) {
            $allowed = ['profile.affiliation', 'profile.affiliation.update', 'profile.affiliation.revoke', 'logout'];

            if (! in_array($request->route()?->getName(), $allowed, true)) {
                return redirect()->route('profile.affiliation')
                    ->with('warning', 'Lengkapi afiliasi institusi Anda terlebih dahulu sebelum menggunakan fitur lain.');
            }
        }

        return $next($request);
    }

    private function hasAffiliation(User $user): bool
    {
        $univ = $user->primaryUniversity();

        return (bool) ($univ
            && $univ->pivot?->faculty_id
            && $univ->pivot?->department_id
            && $univ->pivot?->study_program_id);
    }
}
