<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

/**
 * Fase 0 — Verifikasi isolasi antar-institusi di AdminController.
 *
 * Dua admin dari institusi berbeda TIDAK boleh saling lihat/kelola user
 * satu sama lain. system_admin tetap bisa melihat semua (platform-level).
 *
 * CATATAN: sejak admin non-system tanpa admin_scopes DIKUNCI, admin pada test
 * ini (tanpa scope) tidak dapat melihat/mengelola siapa pun, termasuk di
 * institusinya sendiri.
 */
class AdminInstitutionIsolationTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private Institution $institutionA;
    private Institution $institutionB;
    private User $adminA;
    private User $adminB;
    private User $dosenA;
    private User $dosenB;

    protected function setUp(): void
    {
        parent::setUp();

        // Paksa mode institusi untuk test ini.
        config(['app.mode' => 'institution']);

        // Buat 2 institusi dummy.
        $this->institutionA = Institution::create([
            'app_name' => 'Institusi A',
            'institution_name' => 'Universitas A',
            'email' => 'a@test.com',
        ]);
        $this->institutionB = Institution::create([
            'app_name' => 'Institusi B',
            'institution_name' => 'Universitas B',
            'email' => 'b@test.com',
        ]);

        // Admin A & B (role admin saja, bukan dosen).
        $this->adminA = User::create([
            'name' => 'Admin A',
            'email' => 'admin-a@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institutionA->id,
        ]);
        $this->adminA->assignRole('admin');

        $this->adminB = User::create([
            'name' => 'Admin B',
            'email' => 'admin-b@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institutionB->id,
        ]);
        $this->adminB->assignRole('admin');

        // Dosen A & B (masing-masing di institusi berbeda).
        $this->dosenA = User::create([
            'name' => 'Dosen A',
            'email' => 'dosen-a@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institutionA->id,
        ]);
        $this->dosenA->assignRole('dosen');

        $this->dosenB = User::create([
            'name' => 'Dosen B',
            'email' => 'dosen-b@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institutionB->id,
        ]);
        $this->dosenB->assignRole('dosen');
    }

    protected function tearDown(): void
    {
        config(['app.mode' => 'individual']);
        parent::tearDown();
    }

    public function test_admin_a_cannot_see_any_user_locked_without_scope(): void
    {
        // Admin non-system tanpa admin_scopes DIKUNCI — tidak melihat siapa pun
        // (termasuk dosen di institusinya sendiri).
        $response = $this->actingAs($this->adminA)->get(route('admin.users'));

        $response->assertOk();
        $response->assertDontSee('Dosen A');
        $response->assertDontSee('Dosen B');
        $response->assertDontSee('Admin B');
    }

    public function test_admin_b_cannot_see_any_user_locked_without_scope(): void
    {
        $response = $this->actingAs($this->adminB)->get(route('admin.users'));

        $response->assertOk();
        $response->assertDontSee('Dosen B');
        $response->assertDontSee('Dosen A');
        $response->assertDontSee('Admin A');
    }

    public function test_admin_a_cannot_delete_user_from_institution_b(): void
    {
        $response = $this->actingAs($this->adminA)
            ->delete(route('admin.users.destroy', $this->dosenB));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Tidak dapat mengelola user dari institusi lain.');

        // User B tetap ada.
        $this->assertDatabaseHas('users', ['id' => $this->dosenB->id]);
    }

    public function test_admin_a_cannot_reset_password_of_user_from_institution_b(): void
    {
        $response = $this->actingAs($this->adminA)
            ->post(route('admin.users.reset-password', $this->dosenB), [
                'password' => 'newpassword123',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Tidak dapat mengelola user dari institusi lain.');

        // Password tidak berubah.
        $this->assertNotEquals('newpassword123', $this->dosenB->fresh()->password);
    }

    public function test_admin_tanpa_scope_tidak_bisa_hapus_user_sendiri(): void
    {
        // Admin non-system tanpa admin_scopes DIKUNCI — tidak bisa mengelola siapa pun,
        // termasuk user di institusinya sendiri.
        $response = $this->actingAs($this->adminA)
            ->delete(route('admin.users.destroy', $this->dosenA));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $this->dosenA->id]);
    }

    public function test_admin_a_store_user_gets_own_institution_id(): void
    {
        $response = $this->actingAs($this->adminA)->post(route('admin.users.store'), [
            'name' => 'User Baru A',
            'email' => 'user-baru-a@test.com',
            'password' => 'password',
            'roles' => ['mahasiswa'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'user-baru-a@test.com',
            'institution_id' => $this->institutionA->id,
        ]);
    }

    public function test_system_admin_can_see_all_users_across_institutions(): void
    {
        $systemAdmin = User::create([
            'name' => 'System Admin',
            'email' => 'sysadmin-isolation@test.com',
            'password' => bcrypt('password'),
        ]);
        $systemAdmin->assignRole('system_admin');

        $response = $this->actingAs($systemAdmin)->get(route('admin.users'));

        $response->assertOk();
        $response->assertSee('Dosen A');
        $response->assertSee('Dosen B');
        $response->assertSee('Admin A');
        $response->assertSee('Admin B');
    }

    public function test_system_admin_can_delete_user_from_any_institution(): void
    {
        $systemAdmin = User::create([
            'name' => 'System Admin 2',
            'email' => 'sysadmin-isolation2@test.com',
            'password' => bcrypt('password'),
        ]);
        $systemAdmin->assignRole('system_admin');

        $response = $this->actingAs($systemAdmin)
            ->delete(route('admin.users.destroy', $this->dosenB));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $this->dosenB->id]);
    }

}
