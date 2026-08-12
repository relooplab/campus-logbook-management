<?php

namespace Tests\Feature;

use App\Models\AdminScope;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

/**
 * Fitur "Ubah NIDN" oleh admin (Opsi 2): system admin boleh lintas institusi,
 * admin institusi hanya dalam institusi + cakupan admin_scope-nya. NIDN wajib
 * persis 10 digit angka, unik global (lintas NIM/NIDN), dan perubahan diaudit.
 */
class AdminNidnChangeTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private function systemAdmin(): User
    {
        Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);
        $uid = uniqid();
        $sys = User::create([
            'name' => 'Sys Admin NIDN', 'email' => "sys-nidn-{$uid}@audit.test",
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => "SYS-{$uid}", 'whatsapp' => '628',
        ]);
        $sys->assignRole('system_admin');
        return $sys;
    }

    private function institutionAdmin(): User
    {
        $uid = uniqid();
        $inst = Institution::create([
            'app_name' => 'Test', 'institution_name' => 'NIDN Inst '.$uid,
            'email' => "inst-nidn-{$uid}@test.com",
        ]);
        $a = User::create([
            'name' => 'Admin NIDN', 'email' => "admin-nidn-{$uid}@audit.test",
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => "ADM-{$uid}", 'whatsapp' => '628',
            'institution_id' => $inst->id,
        ]);
        $a->assignRole('admin');
        return $a;
    }

    /**
     * Dosen target dengan NIDN 10-digit unik.
     */
    private function dosen(?int $institutionId = null, ?string $nidn = null): User
    {
        $uid = uniqid();
        $d = User::create([
            'name' => 'Dosen NIDN', 'email' => "dst-{$uid}@audit.test",
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => "DOS-{$uid}", 'whatsapp' => '628',
            'institution_id' => $institutionId,
            'nidn' => $nidn ?? (string) random_int(1000000000, 9999999999),
        ]);
        $d->assignRole('dosen');
        return $d;
    }

    private function grantFacultyScope(User $admin, string $label): int
    {
        $svc = app(\App\Services\OrganizationalDirectoryService::class);
        $univ = $svc->findOrCreateUniversity("Univ {$label} ".uniqid());
        $faculty = $svc->findOrCreateFaculty($univ, "Fakultas {$label} ".uniqid());
        AdminScope::create([
            'user_id' => $admin->id,
            'institution_id' => $admin->institution_id,
            'scope_type' => 'faculty',
            'scope_id' => $faculty->id,
            'status' => 'active',
        ]);

        return $univ->id;
    }

    public function test_system_admin_can_change_dosen_nidn(): void
    {
        $dosen = $this->dosen();
        $new = (string) random_int(1000000000, 9999999999);
        $response = $this->actingAs($this->systemAdmin())
            ->put(route('admin.users.nidn', $dosen), ['nidn' => $new]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame($new, $dosen->fresh()->nidn);
    }

    public function test_system_admin_can_change_nidn_of_admin_account(): void
    {
        $admin = $this->institutionAdmin();
        $new = (string) random_int(1000000000, 9999999999);
        $response = $this->actingAs($this->systemAdmin())
            ->put(route('admin.users.nidn', $admin), ['nidn' => $new]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame($new, $admin->fresh()->nidn);
    }

    public function test_institution_admin_can_change_nidn_in_own_institution_with_scope(): void
    {
        $admin = $this->institutionAdmin();
        $target = $this->dosen($admin->institution_id);
        $univId = $this->grantFacultyScope($admin, 'Sendiri');

        $faculty = \App\Models\Faculty::firstWhere('university_id', $univId);
        $target->universities()->attach($univId, [
            'faculty_id' => $faculty->id, 'department_id' => null, 'study_program_id' => null,
            'is_primary' => true, 'status' => 'active',
        ]);

        $new = (string) random_int(1000000000, 9999999999);
        $response = $this->actingAs($admin)
            ->put(route('admin.users.nidn', $target), ['nidn' => $new]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame($new, $target->fresh()->nidn);
    }

    public function test_institution_admin_cannot_change_nidn_of_other_institution(): void
    {
        $admin = $this->institutionAdmin();

        // Institusi kedua yang BEDA dari milik admin.
        $otherInst = Institution::create([
            'app_name' => 'Test', 'institution_name' => 'Inst Reform '.uniqid(),
            'email' => 'other-inst-'.uniqid().'@test.com',
        ]);
        $other = $this->dosen($otherInst->id);
        $this->grantFacultyScope($admin, 'Lain');
        $before = $other->nidn;

        $response = $this->actingAs($admin)
            ->put(route('admin.users.nidn', $other), ['nidn' => (string) random_int(1000000000, 9999999999)]);

        $response->assertForbidden();
        $this->assertSame($before, $other->fresh()->nidn);
    }

    public function test_nidn_must_be_exactly_10_digits(): void
    {
        $dosen = $this->dosen();
        $sys = $this->systemAdmin();

        $this->actingAs($sys)
            ->put(route('admin.users.nidn', $dosen), ['nidn' => '12345'])
            ->assertSessionHasErrors('nidn');

        // Non-numerik (bukan 10 digit angka)
        $this->actingAs($sys)
            ->put(route('admin.users.nidn', $dosen), ['nidn' => '000000000a'])
            ->assertSessionHasErrors('nidn');

        $this->assertNotEmpty($dosen->fresh()->nidn);
    }

    public function test_nidn_must_be_unique_globally(): void
    {
        $dosenA = $this->dosen();
        $dosenB = $this->dosen();

        // Set NIDN dosenB menjadi milik dosenA → harus ditolak (unik global).
        $response = $this->actingAs($this->systemAdmin())
            ->put(route('admin.users.nidn', $dosenB), ['nidn' => $dosenA->nidn]);

        $response->assertSessionHasErrors('nidn');
        $this->assertNotSame($dosenA->nidn, $dosenB->fresh()->nidn);
    }
}
