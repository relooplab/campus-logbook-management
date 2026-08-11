<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Audit log untuk aksi admin sensitif & login (channel 'audit').
 */
class AuditAdminActionsTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private function auditPath(): string
    {
        return storage_path('logs/audit-'.now()->format('Y-m-d').'.log');
    }

    private function auditSize(): int
    {
        $p = $this->auditPath();
        return is_file($p) ? filesize($p) : 0;
    }

    private function auditAppend(int $size): string
    {
        $p = $this->auditPath();
        $content = is_file($p) ? (string) file_get_contents($p) : '';
        return $size > 0 ? substr($content, $size) : $content;
    }

    private function assertAuditLogged(int $size, string $label): void
    {
        $this->assertStringContainsString($label, $this->auditAppend($size), "Audit log harus memuat: {$label}");
    }

    private function systemAdmin(): User
    {
        Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);
        $sys = User::create([
            'name' => 'Sys Admin Audit', 'email' => 'sys@audit.test', 'password' => bcrypt('x'),
            'registration_status' => 'active', 'nim' => 'SYS-A', 'whatsapp' => '628',
        ]);
        $sys->assignRole('system_admin');
        foreach (['admin.users', 'admin.institution'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $sys->givePermissionTo($p);
        }

        return $sys;
    }

    public function test_login_success_and_failure_are_logged(): void
    {
        User::create([
            'name' => 'Loginx', 'email' => 'loginx@audit.test', 'password' => bcrypt('secret'),
            'registration_status' => 'active', 'nim' => 'NIM-LX', 'whatsapp' => '6281',
        ]);

        $s1 = $this->auditSize();
        $this->post(route('login.attempt'), ['email' => 'loginx@audit.test', 'password' => 'wrong']);
        $this->assertAuditLogged($s1, 'Login gagal');

        $s2 = $this->auditSize();
        $this->post(route('login.attempt'), ['email' => 'loginx@audit.test', 'password' => 'secret']);
        $this->assertAuditLogged($s2, 'Login berhasil');
    }

    public function test_store_user_is_logged(): void
    {
        $sys = $this->systemAdmin();
        $s = $this->auditSize();
        $this->actingAs($sys)->post(route('admin.users.store'), [
            'name' => 'Budi', 'email' => 'budi@audit.test', 'password' => 'secret123',
            'roles' => ['mahasiswa'], 'nim' => 'NIM-B',
        ]);
        $this->assertAuditLogged($s, 'Admin membuat pengguna');
    }

    public function test_reset_password_is_logged(): void
    {
        $sys = $this->systemAdmin();
        $u = User::create(['name' => 'R', 'email' => 'r@audit.test', 'password' => bcrypt('x'), 'nim' => 'NIM-R', 'whatsapp' => '628']);
        $s = $this->auditSize();
        $this->actingAs($sys)->post(route('admin.users.reset-password', $u), ['password' => 'newsecret']);
        $this->assertAuditLogged($s, 'Admin mereset password user');
    }

    public function test_destroy_user_is_logged(): void
    {
        $sys = $this->systemAdmin();
        $u = User::create(['name' => 'D', 'email' => 'd@audit.test', 'password' => bcrypt('x'), 'nim' => 'NIM-D', 'whatsapp' => '628']);
        $s = $this->auditSize();
        $this->actingAs($sys)->delete(route('admin.users.destroy', $u));
        $this->assertAuditLogged($s, 'Admin menghapus user');
    }

    public function test_update_institution_is_logged(): void
    {
        $sys = $this->systemAdmin();
        $s = $this->auditSize();
        $this->actingAs($sys)->post(route('admin.institution.update'), [
            'app_name' => 'Test App', 'institution_name' => 'Test University',
            'max_upload_size_mb' => 10, 'allowed_file_types' => 'pdf',
        ]);
        $this->assertAuditLogged($s, 'Admin mengubah profil institusi / pengaturan');
    }

    public function test_update_permissions_is_logged(): void
    {
        $sys = $this->systemAdmin();
        $s = $this->auditSize();
        $this->actingAs($sys)->post(route('admin.system.permissions.update'), ['permissions' => []]);
        $this->assertAuditLogged($s, 'Admin mengubah hak akses (permissions) role');
    }
}
