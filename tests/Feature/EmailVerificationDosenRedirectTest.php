<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regresi redirect-loop saat verifikasi email WAJIB aktif untuk dosen baru
 * yang belum berafiliasi & belum verifikasi email.
 *
 * Sebelum perbaikan: `verification.notice` berada di grup yang sama dengan
 * `ensure.dosen.affiliation`, sehingga dosen belum-afiliasi di-redirect balik
 * ke afiliasi → loop (ERR_TOO_MANY_REDIRECTS). Setelah perbaikan, notice/send
 * pindah ke grup auth-only dan bisa diakses.
 */
class EmailVerificationDosenRedirectTest extends TestCase
{
    use DatabaseTransactions;

    private function unverifiedUnaffiliatedDosen(): User
    {
        Role::firstOrCreate(['name' => 'dosen', 'guard_name' => 'web']);

        $u = User::create([
            'name' => 'Dosen Baru', 'email' => 'dbr-'.uniqid().'@t.test',
            'password' => bcrypt('x'), 'registration_status' => 'active',
        ]);
        $u->forceFill(['email_verified_at' => null])->save();
        $u->assignRole('dosen');

        return $u;
    }

    private function enableLocks(): void
    {
        config(['app.enforce_email_verification' => true]);
        config(['app.enforce_dosen_affiliation' => true]);

        $inst = Institution::active();
        $inst->email_verification_override = true;
        $inst->save();
        Institution::flush();
    }

    public function test_unaffiliated_dosen_can_open_verification_notice_without_loop(): void
    {
        $this->enableLocks();
        $dosen = $this->unverifiedUnaffiliatedDosen();

        // Halaman notice harus 200 (bukan di-redirect balik ke afiliasi / loop).
        $this->actingAs($dosen)->get(route('verification.notice'))->assertOk();

        // Halaman afiliasi justru diarahkan ke notice (bukan loop tak berujung).
        $this->actingAs($dosen)->get(route('profile.affiliation'))
            ->assertRedirect(route('verification.notice'));
    }
}