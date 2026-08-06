<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DirectorySubscription;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\Plan;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Fase C — Gate pembuatan akun admin di mode institusi:
 * - Institusi harus punya minimal 1 directory_subscriptions aktif.
 * - Admin_scope (jika ada) harus ter-cover langganan aktif.
 * - Mode individual tidak terpengaruh sama sekali.
 */
class AdminCreationGateTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private OrganizationalDirectoryService $service;
    private Institution $institution;
    private University $univ;
    private Faculty $faculty;
    private Department $dept;
    private StudyProgram $prodi;
    private Plan $plan;
    private User $systemAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrganizationalDirectoryService::class);

        // Paksa mode institusi.
        config(['app.mode' => 'institution']);

        $this->institution = Institution::create([
            'app_name' => 'Gate Test',
            'institution_name' => 'Universitas Gate Test',
            'email' => 'gate@test.com',
        ]);

        $this->univ = $this->service->findOrCreateUniversity('Universitas Gate Test');
        $this->faculty = $this->service->findOrCreateFaculty($this->univ, 'Fakultas Teknik');
        $this->dept = $this->service->findOrCreateDepartment($this->faculty, 'Departemen Teknik Informatika');
        $this->prodi = $this->service->findOrCreateStudyProgram($this->dept, 'S1 Teknik Informatika');

        $this->plan = Plan::create([
            'name' => 'gate_plan',
            'label' => 'Gate Plan',
            'price' => 100000,
            'period' => 'monthly',
            'features' => ['storage_mb' => 10240, 'export' => true, 'import' => true],
            'is_active' => true,
        ]);

        // Dosen di institusi ini terafiliasi ke prodi.
        $this->dosen->update(['institution_id' => $this->institution->id]);
        $this->service->attachUserToUniversity($this->dosen, $this->univ, $this->faculty, $this->dept, $this->prodi, true);

        // System admin.
        $this->systemAdmin = User::create([
            'name' => 'System Admin Gate',
            'email' => 'sysadmin-gate@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->systemAdmin->assignRole('system_admin');
    }

    protected function tearDown(): void
    {
        config(['app.mode' => 'individual']);
        parent::tearDown();
    }

    public function test_admin_creation_blocked_when_no_active_subscription(): void
    {
        $response = $this->actingAs($this->systemAdmin)->post(route('admin.system.admins.store'), [
            'name' => 'Admin Baru',
            'email' => 'admin-baru@test.com',
            'password' => 'password',
            'institution_id' => $this->institution->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Aktifkan langganan institusi dulu sebelum membuat akun admin.');

        $this->assertDatabaseMissing('users', ['email' => 'admin-baru@test.com']);
    }

    public function test_admin_creation_allowed_when_subscription_active(): void
    {
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'plan_id' => $this->plan->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
            'assigned_by' => $this->systemAdmin->id,
        ]);

        $response = $this->actingAs($this->systemAdmin)->post(route('admin.system.admins.store'), [
            'name' => 'Admin Baru',
            'email' => 'admin-baru@test.com',
            'password' => 'password',
            'institution_id' => $this->institution->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'admin-baru@test.com',
            'institution_id' => $this->institution->id,
        ]);
    }

    public function test_admin_creation_with_scope_requires_covered_subscription(): void
    {
        // Langganan aktif di prodi.
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'plan_id' => $this->plan->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        // Scope ke prodi yang TIDAK ter-cover -> ditolak.
        $prodiLain = $this->service->findOrCreateStudyProgram($this->dept, 'S1 Sistem Informasi');

        $response = $this->actingAs($this->systemAdmin)->post(route('admin.system.admins.store'), [
            'name' => 'Admin Scope',
            'email' => 'admin-scope@test.com',
            'password' => 'password',
            'institution_id' => $this->institution->id,
            'scopes' => [
                ['scope_type' => 'study_program', 'scope_id' => $prodiLain->id],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Scope admin tidak ter-cover langganan aktif. Aktifkan langganan node terkait dulu.');

        $this->assertDatabaseMissing('users', ['email' => 'admin-scope@test.com']);
    }

    public function test_admin_creation_with_covered_scope_succeeds(): void
    {
        // Langganan aktif di prodi.
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'plan_id' => $this->plan->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        $response = $this->actingAs($this->systemAdmin)->post(route('admin.system.admins.store'), [
            'name' => 'Admin Scope OK',
            'email' => 'admin-scope-ok@test.com',
            'password' => 'password',
            'institution_id' => $this->institution->id,
            'scopes' => [
                ['scope_type' => 'study_program', 'scope_id' => $this->prodi->id],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $user = User::where('email', 'admin-scope-ok@test.com')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('admin_scopes', [
            'user_id' => $user->id,
            'institution_id' => $this->institution->id,
            'scope_type' => 'study_program',
            'scope_id' => $this->prodi->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_creation_in_individual_mode_unaffected(): void
    {
        // Mode individual.
        config(['app.mode' => 'individual']);

        // Tidak ada langganan direktori sama sekali.
        $response = $this->actingAs($this->systemAdmin)->post(route('admin.system.admins.store'), [
            'name' => 'Admin Individual',
            'email' => 'admin-individual@test.com',
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'admin-individual@test.com',
            'institution_id' => null,
        ]);
    }

    public function test_admin_creation_with_institution_id_requires_subscription(): void
    {
        // Setelah refactor SaaS unified, institution_id selalu diterima
        // (tidak lagi di-gate oleh APP_MODE), tapi tetap wajib punya langganan aktif.

        // institution_id tanpa langganan aktif -> ditolak.
        $response = $this->actingAs($this->systemAdmin)->post(route('admin.system.admins.store'), [
            'name' => 'Admin Individual 2',
            'email' => 'admin-individual2@test.com',
            'password' => 'password',
            'institution_id' => $this->institution->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Aktifkan langganan institusi dulu sebelum membuat akun admin.');

        $this->assertDatabaseMissing('users', ['email' => 'admin-individual2@test.com']);
    }
}