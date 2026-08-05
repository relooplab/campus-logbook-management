<?php

namespace Tests\Feature;

use App\Models\AdminScope;
use App\Models\Department;
use App\Models\DirectorySubscription;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\Plan;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Verifikasi hierarki pembuatan admin:
 * - Admin dengan scope (fakultas) bisa membuat admin di bawahnya (departemen/prodi).
 * - Admin tidak bisa membuat admin di luar cakupan scope-nya.
 * - Admin institusi penuh (tanpa scope) tidak bisa membuat sub-admin.
 */
class SubAdminHierarchyTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private OrganizationalDirectoryService $service;
    private Institution $institution;
    private University $univ;
    private Faculty $facultyA;
    private Faculty $facultyB;
    private Department $deptA;
    private StudyProgram $prodiA;
    private Plan $plan;
    private User $facultyAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Pastikan permission admin.create-admin ada (test DB mungkin belum migrasi).
        \Spatie\Permission\Models\Permission::findOrCreate('admin.create-admin', 'web');

        $this->service = app(OrganizationalDirectoryService::class);

        // Paksa mode institusi.
        config(['app.mode' => 'institution']);

        $this->institution = Institution::create([
            'app_name' => 'Hierarki Test',
            'institution_name' => 'Universitas Hierarki Test',
            'email' => 'hierarki@test.com',
        ]);

        // Direktori: Fakultas A (dengan dept A + prodi A), Fakultas B.
        $this->univ = $this->service->findOrCreateUniversity('Universitas Hierarki Test');
        $this->facultyA = $this->service->findOrCreateFaculty($this->univ, 'Fakultas Teknik');
        $this->deptA = $this->service->findOrCreateDepartment($this->facultyA, 'Departemen Teknik Informatika');
        $this->prodiA = $this->service->findOrCreateStudyProgram($this->deptA, 'S1 Teknik Informatika');
        $this->facultyB = $this->service->findOrCreateFaculty($this->univ, 'Fakultas Ekonomi');

        $this->plan = Plan::create([
            'name' => 'hierarki_plan',
            'label' => 'Hierarki Plan',
            'price' => 100000,
            'period' => 'monthly',
            'features' => ['storage_mb' => 10240, 'export' => true, 'import' => true],
            'is_active' => true,
        ]);

        // Langganan aktif di universitas (meng-cover semua).
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_UNIVERSITY,
            'scope_id' => $this->univ->id,
            'plan_id' => $this->plan->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        // Admin fakultas A (scope = fakultas A).
        $this->facultyAdmin = User::create([
            'name' => 'Admin Fakultas',
            'email' => 'admin-fakultas@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $this->facultyAdmin->assignRole('admin');
        $this->facultyAdmin->givePermissionTo('admin.create-admin');

        AdminScope::create([
            'user_id' => $this->facultyAdmin->id,
            'institution_id' => $this->institution->id,
            'scope_type' => AdminScope::SCOPE_FACULTY,
            'scope_id' => $this->facultyA->id,
            'status' => AdminScope::STATUS_ACTIVE,
        ]);
    }

    protected function tearDown(): void
    {
        config(['app.mode' => 'individual']);
        parent::tearDown();
    }

    public function test_scoped_admin_can_create_sub_admin_with_descendant_scope(): void
    {
        // Admin fakultas A buat admin prodi A (di bawah fakultas A).
        $response = $this->actingAs($this->facultyAdmin)->post(route('admin.sub-admins.store'), [
            'name' => 'Admin Prodi',
            'email' => 'admin-prodi@test.com',
            'password' => 'password',
            'scopes' => [
                ['scope_type' => 'study_program', 'scope_id' => $this->prodiA->id],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $user = User::where('email', 'admin-prodi@test.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertDatabaseHas('admin_scopes', [
            'user_id' => $user->id,
            'institution_id' => $this->institution->id,
            'scope_type' => 'study_program',
            'scope_id' => $this->prodiA->id,
            'status' => 'active',
        ]);
    }

    public function test_scoped_admin_cannot_create_sub_admin_outside_scope(): void
    {
        // Admin fakultas A coba buat admin fakultas B (di luar scope).
        $response = $this->actingAs($this->facultyAdmin)->post(route('admin.sub-admins.store'), [
            'name' => 'Admin Fakultas B',
            'email' => 'admin-fakultas-b@test.com',
            'password' => 'password',
            'scopes' => [
                ['scope_type' => 'faculty', 'scope_id' => $this->facultyB->id],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Scope admin baru harus berada di bawah cakupan scope Anda.');

        $this->assertDatabaseMissing('users', ['email' => 'admin-fakultas-b@test.com']);
    }

    public function test_scoped_admin_cannot_create_sub_admin_with_wider_scope(): void
    {
        // Admin fakultas A coba buat admin dengan scope lebih luas (universitas).
        // Tapi admin_scopes tidak mendukung university, jadi coba scope fakultas lain
        // yang bukan turunan dari fakultas A.
        $deptB = $this->service->findOrCreateDepartment($this->facultyB, 'Departemen Ekonomi');

        $response = $this->actingAs($this->facultyAdmin)->post(route('admin.sub-admins.store'), [
            'name' => 'Admin Dept B',
            'email' => 'admin-dept-b@test.com',
            'password' => 'password',
            'scopes' => [
                ['scope_type' => 'department', 'scope_id' => $deptB->id],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Scope admin baru harus berada di bawah cakupan scope Anda.');

        $this->assertDatabaseMissing('users', ['email' => 'admin-dept-b@test.com']);
    }

    public function test_full_institution_admin_cannot_create_sub_admin(): void
    {
        // Admin institusi penuh (tanpa admin_scopes).
        $fullAdmin = User::create([
            'name' => 'Admin Penuh',
            'email' => 'admin-penuh@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $fullAdmin->assignRole('admin');
        $fullAdmin->givePermissionTo('admin.create-admin');

        $response = $this->actingAs($fullAdmin)->post(route('admin.sub-admins.store'), [
            'name' => 'Admin X',
            'email' => 'admin-x@test.com',
            'password' => 'password',
            'scopes' => [
                ['scope_type' => 'study_program', 'scope_id' => $this->prodiA->id],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Anda tidak memiliki scope admin. Hanya admin dengan scope yang dapat membuat admin di bawahnya.');

        $this->assertDatabaseMissing('users', ['email' => 'admin-x@test.com']);
    }

    public function test_system_admin_can_still_create_admins(): void
    {
        $systemAdmin = User::create([
            'name' => 'System Admin Hierarki',
            'email' => 'sysadmin-hierarki@test.com',
            'password' => bcrypt('password'),
        ]);
        $systemAdmin->assignRole('system_admin');
        // Role system_admin di test DB tidak punya permission (auto-created).
        $systemAdmin->givePermissionTo('system.admins');

        // Perlu user di institusi ini dengan afiliasi universitas agar
        // institutionHasActiveDirectorySubscription() true.
        $dosenForAffiliation = User::create([
            'name' => 'Dosen Afiliasi',
            'email' => 'dosen-afiliasi@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $dosenForAffiliation->assignRole('dosen');
        $this->service->attachUserToUniversity($dosenForAffiliation, $this->univ, $this->facultyB, null, null, true);

        // system_admin buat admin fakultas B (via storeSystemAdmin).
        $response = $this->actingAs($systemAdmin)->post(route('admin.system.admins.store'), [
            'name' => 'Admin Fakultas B',
            'email' => 'admin-fakultas-b2@test.com',
            'password' => 'password',
            'institution_id' => $this->institution->id,
            'scopes' => [
                ['scope_type' => 'faculty', 'scope_id' => $this->facultyB->id],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'admin-fakultas-b2@test.com']);
    }

    public function test_sub_admin_creation_requires_admin_create_admin_permission(): void
    {
        // Buat role custom tanpa admin.create-admin.
        $roleNoPerm = \Spatie\Permission\Models\Role::findOrCreate('admin_no_perm', 'web');
        $roleNoPerm->syncPermissions(['admin.users']);

        $adminNoPerm = User::create([
            'name' => 'Admin No Perm',
            'email' => 'admin-no-perm@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $adminNoPerm->assignRole($roleNoPerm);

        AdminScope::create([
            'user_id' => $adminNoPerm->id,
            'institution_id' => $this->institution->id,
            'scope_type' => AdminScope::SCOPE_FACULTY,
            'scope_id' => $this->facultyA->id,
            'status' => AdminScope::STATUS_ACTIVE,
        ]);

        // Method storeSubAdmin menolak (403) user tanpa permission admin.create-admin.
        $response = $this->actingAs($adminNoPerm)->post(route('admin.sub-admins.store'), [
            'name' => 'Admin No Perm Child',
            'email' => 'admin-no-perm-child@test.com',
            'password' => 'password',
            'scopes' => [
                ['scope_type' => 'study_program', 'scope_id' => $this->prodiA->id],
            ],
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'admin-no-perm-child@test.com']);
    }
}
