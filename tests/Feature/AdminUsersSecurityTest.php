<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Verifikasi bahwa fitur yang hanya untuk system admin TIDAK bisa diakses
 * oleh admin institusi atau user biasa. Juga memastikan admin institusi
 * hanya bisa mengelola user dalam wewenangnya.
 */
class AdminUsersSecurityTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private function systemAdmin(): User
    {
        Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);
        $uid = uniqid();
        $sys = User::create([
            'name' => 'Sys Admin Security', 'email' => "sys-sec-{$uid}@audit.test",
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => "SYS-SEC-{$uid}", 'whatsapp' => '628',
        ]);
        $sys->assignRole('system_admin');
        return $sys;
    }

    private function institutionAdmin(string $suffix = ''): User
    {
        $uid = uniqid();
        $inst = Institution::create([
            'app_name' => 'Test', 'institution_name' => 'Test Inst '.$uid,
            'email' => "inst-{$uid}@test.com",
        ]);
        $a = User::create([
            'name' => 'Admin Inst '.$uid.$suffix, 'email' => "inst-admin-{$uid}{$suffix}@audit.test",
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => "ADM-{$uid}", 'whatsapp' => '628',
            'institution_id' => $inst->id,
        ]);
        $a->assignRole('admin');
        return $a;
    }

    private function plainDosen(): User
    {
        $uid = uniqid();
        $d = User::create([
            'name' => 'Dosen Biasa', 'email' => "dosen-{$uid}@audit.test",
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => "DOS-{$uid}", 'whatsapp' => '628',
        ]);
        $d->assignRole('dosen');
        return $d;
    }

    public function test_system_admin_can_view_users_page(): void
    {
        $response = $this->actingAs($this->systemAdmin())->get(route('admin.users'));
        $response->assertOk();
        $response->assertSee('Kelola Pengguna');
    }

    public function test_institution_admin_can_view_users_page(): void
    {
        $response = $this->actingAs($this->institutionAdmin())->get(route('admin.users'));
        $response->assertOk();
    }

    public function test_plain_dosen_cannot_view_users_page(): void
    {
        $response = $this->actingAs($this->plainDosen())->get(route('admin.users'));
        $response->assertForbidden();
    }

    public function test_institution_admin_cannot_access_system_plan_settings(): void
    {
        $sys = $this->systemAdmin();
        $target = $this->plainDosen();
        $response = $this->actingAs($this->institutionAdmin())
            ->get(route('admin.system.users.plan', $target));
        $response->assertForbidden();
    }

    public function test_institution_admin_cannot_update_system_plan(): void
    {
        $target = $this->plainDosen();
        $response = $this->actingAs($this->institutionAdmin())
            ->post(route('admin.system.users.plan.update', $target), [
                'plan_id' => 1,
            ]);
        $response->assertForbidden();
    }

    public function test_institution_admin_cannot_update_user_institution(): void
    {
        $admin = $this->institutionAdmin();
        $target = $this->plainDosen();
        $response = $this->actingAs($admin)
            ->post(route('admin.system.users.institution', $target), [
                'institution_id' => $admin->institution_id,
            ]);
        $response->assertForbidden();
    }

    public function test_institution_admin_cannot_access_system_settings(): void
    {
        $response = $this->actingAs($this->institutionAdmin())
            ->get(route('admin.system.settings'));
        $response->assertForbidden();
    }

    public function test_institution_admin_cannot_access_system_permissions(): void
    {
        $response = $this->actingAs($this->institutionAdmin())
            ->get(route('admin.system.permissions'));
        $response->assertForbidden();
    }

    public function test_institution_admin_cannot_access_system_directory(): void
    {
        $response = $this->actingAs($this->institutionAdmin())
            ->get(route('admin.system.directory'));
        $response->assertForbidden();
    }

    public function test_institution_admin_cannot_access_system_backups(): void
    {
        $response = $this->actingAs($this->institutionAdmin())
            ->get(route('admin.system.backup'));
        $response->assertForbidden();
    }

    public function test_plain_dosen_cannot_access_system_plan(): void
    {
        $target = $this->plainDosen();
        $response = $this->actingAs($this->plainDosen())
            ->get(route('admin.system.users.plan', $target));
        $response->assertForbidden();
    }

    public function test_institution_admin_cannot_destroy_system_admin(): void
    {
        $admin = $this->institutionAdmin();
        $sys = $this->systemAdmin();
        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $sys));
        $response->assertSessionHas('error');
        $this->assertNotNull(User::find($sys->id), 'System admin tidak boleh dihapus oleh admin institusi.');
    }

    public function test_institution_admin_cannot_reset_system_admin_password(): void
    {
        $admin = $this->institutionAdmin();
        $sys = $this->systemAdmin();
        $response = $this->actingAs($admin)->post(route('admin.users.reset-password', $sys), [
            'password' => 'hacked123',
        ]);
        $response->assertSessionHas('error');
    }

    public function test_bulk_users_respects_can_manage_user(): void
    {
        $admin = $this->institutionAdmin();
        // admin_scopes.scope_type CHECK constraint: hanya study_program|department|faculty.
        // Beri admin scope fakultas supaya canManageUser mengizinkan own-institution user.
        $svc = app(\App\Services\OrganizationalDirectoryService::class);
        $univ = $svc->findOrCreateUniversity('Univ Bulk '.uniqid());
        $faculty = $svc->findOrCreateFaculty($univ, 'Fakultas Bulk '.uniqid());
        \App\Models\AdminScope::create([
            'user_id' => $admin->id,
            'institution_id' => $admin->institution_id,
            'scope_type' => 'faculty',
            'scope_id' => $faculty->id,
            'status' => 'active',
        ]);
        $own = User::create([
            'name' => 'User Sendiri', 'email' => 'own-'.uniqid().'@audit.test',
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => 'OWN-'.uniqid(), 'whatsapp' => '628',
            'institution_id' => $admin->institution_id,
        ]);
        $own->assignRole('mahasiswa');
        $own->universities()->attach($univ->id, [
            'faculty_id' => $faculty->id, 'department_id' => null, 'study_program_id' => null,
            'is_primary' => true, 'status' => 'active',
        ]);
        $other = User::create([
            'name' => 'User Lain', 'email' => 'other-'.uniqid().'@audit.test',
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => 'OTH-'.uniqid(), 'whatsapp' => '628',
        ]);
        $other->assignRole('mahasiswa');

        $response = $this->actingAs($admin)->post(route('admin.users.bulk'), [
            'ids' => [$own->id, $other->id],
            'action' => 'reject',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame('rejected', $own->fresh()->registration_status);
        $this->assertNotSame('rejected', $other->fresh()->registration_status, 'User di luar wewenang tidak boleh diproses.');
    }
}
