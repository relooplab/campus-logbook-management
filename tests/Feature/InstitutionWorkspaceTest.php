<?php

namespace Tests\Feature;

use App\Models\AdminScope;
use App\Models\Department;
use App\Models\DirectorySubscription;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\InstitutionWorkspace;
use App\Models\Plan;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Verifikasi fitur Workspace Institusi:
 * - Akses dosen se-prodi (hanya prodi sendiri).
 * - Akses custom dosen.
 * - Admin di simpul sama bisa akses + kelola.
 * - Admin level berbeda tidak bisa akses.
 * - Fingerprint uploader.
 */
class InstitutionWorkspaceTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private OrganizationalDirectoryService $service;
    private Institution $institution;
    private University $univ;
    private Faculty $faculty;
    private Department $dept;
    private StudyProgram $prodiA;
    private Plan $plan;
    private User $adminProdiA;
    private User $dosenA;
    private User $dosenBOtherProdi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrganizationalDirectoryService::class);

        // Paksa mode institusi.
        config(['app.mode' => 'institution']);

        $this->institution = Institution::create([
            'app_name' => 'WS Test',
            'institution_name' => 'Universitas WS Test',
            'email' => 'ws@test.com',
        ]);

        $this->univ = $this->service->findOrCreateUniversity('Universitas WS Test');
        $this->faculty = $this->service->findOrCreateFaculty($this->univ, 'Fakultas Teknik');
        $this->dept = $this->service->findOrCreateDepartment($this->faculty, 'Departemen Teknik Informatika');
        $this->prodiA = $this->service->findOrCreateStudyProgram($this->dept, 'Prodi A');
        $prodiB = $this->service->findOrCreateStudyProgram($this->dept, 'Prodi B');

        $this->plan = Plan::create([
            'name' => 'ws_plan',
            'label' => 'WS Plan',
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

        // Admin prodi A.
        $this->adminProdiA = User::create([
            'name' => 'Admin Prodi A',
            'email' => 'admin-ws-a@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $this->adminProdiA->assignRole('admin');
        AdminScope::create([
            'user_id' => $this->adminProdiA->id,
            'institution_id' => $this->institution->id,
            'scope_type' => AdminScope::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodiA->id,
            'status' => AdminScope::STATUS_ACTIVE,
        ]);

        // Dosen A (prodi A) & dosen B (prodi B).
        $this->dosenA = User::create([
            'name' => 'Dosen A',
            'email' => 'dosen-ws-a@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $this->dosenA->assignRole('dosen');
        $this->service->attachUserToUniversity($this->dosenA, $this->univ, $this->faculty, $this->dept, $this->prodiA, true);

        $this->dosenBOtherProdi = User::create([
            'name' => 'Dosen B',
            'email' => 'dosen-ws-b@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $this->dosenBOtherProdi->assignRole('dosen');
        $this->service->attachUserToUniversity($this->dosenBOtherProdi, $this->univ, $this->faculty, $this->dept, $prodiB, true);
    }

    protected function tearDown(): void
    {
        config(['app.mode' => 'individual']);
        parent::tearDown();
    }

    public function test_admin_can_create_workspace_at_their_scope(): void
    {
        $response = $this->actingAs($this->adminProdiA)->post(route('workspace-institusi.store'), [
            'scope_type' => 'study_program',
            'scope_id' => $this->prodiA->id,
            'name' => 'Workspace Prodi A',
            'access_mode' => 'hierarchical',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('institution_workspaces', [
            'scope_type' => 'study_program',
            'scope_id' => $this->prodiA->id,
            'name' => 'Workspace Prodi A',
            'created_by' => $this->adminProdiA->id,
        ]);
    }

    public function test_dosen_same_prodi_can_access_workspace_by_default(): void
    {
        $workspace = InstitutionWorkspace::create([
            'institution_id' => $this->institution->id,
            'scope_type' => 'study_program',
            'scope_id' => $this->prodiA->id,
            'name' => 'WS Prodi A',
            'access_mode' => 'hierarchical',
            'created_by' => $this->adminProdiA->id,
        ]);

        $this->assertTrue($workspace->isAccessibleBy($this->dosenA));
        $this->assertFalse($workspace->isAccessibleBy($this->dosenBOtherProdi));
    }

    public function test_dosen_other_prodi_cannot_access(): void
    {
        $workspace = InstitutionWorkspace::create([
            'institution_id' => $this->institution->id,
            'scope_type' => 'study_program',
            'scope_id' => $this->prodiA->id,
            'name' => 'WS Prodi A',
            'access_mode' => 'hierarchical',
            'created_by' => $this->adminProdiA->id,
        ]);

        $this->assertFalse($workspace->isAccessibleBy($this->dosenBOtherProdi));

        // Dosen B cannot access via HTTP too.
        $response = $this->actingAs($this->dosenBOtherProdi)->get(route('workspace-institusi.show', $workspace));
        $response->assertForbidden();
    }

    public function test_custom_grant_allows_other_prodi_dosen(): void
    {
        $workspace = InstitutionWorkspace::create([
            'institution_id' => $this->institution->id,
            'scope_type' => 'study_program',
            'scope_id' => $this->prodiA->id,
            'name' => 'WS Prodi A',
            'access_mode' => 'custom',
            'created_by' => $this->adminProdiA->id,
        ]);
        $workspace->allowedUsers()->attach($this->dosenBOtherProdi->id);

        $this->assertTrue($workspace->isAccessibleBy($this->dosenBOtherProdi));
    }

    public function test_admin_same_node_can_manage(): void
    {
        $workspace = InstitutionWorkspace::create([
            'institution_id' => $this->institution->id,
            'scope_type' => 'study_program',
            'scope_id' => $this->prodiA->id,
            'name' => 'WS Prodi A',
            'access_mode' => 'hierarchical',
            'created_by' => $this->adminProdiA->id,
        ]);

        // Admin prodi A bisa kelola.
        $this->assertTrue($workspace->canManage($this->adminProdiA));
    }

    public function test_admin_different_level_cannot_access(): void
    {
        // Admin di fakultas (bukan prodi A).
        $adminFaculty = User::create([
            'name' => 'Admin Fakultas',
            'email' => 'admin-ws-f@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $adminFaculty->assignRole('admin');
        AdminScope::create([
            'user_id' => $adminFaculty->id,
            'institution_id' => $this->institution->id,
            'scope_type' => AdminScope::SCOPE_FACULTY,
            'scope_id' => $this->faculty->id,
            'status' => AdminScope::STATUS_ACTIVE,
        ]);

        $workspace = InstitutionWorkspace::create([
            'institution_id' => $this->institution->id,
            'scope_type' => 'study_program',
            'scope_id' => $this->prodiA->id,
            'name' => 'WS Prodi A',
            'access_mode' => 'hierarchical',
            'created_by' => $this->adminProdiA->id,
        ]);

        // Admin fakultas (level beda) tidak bisa akses.
        $this->assertFalse($workspace->isAccessibleBy($adminFaculty));
        $this->assertFalse($workspace->canManage($adminFaculty));
    }

    public function test_upload_records_uploader_fingerprint(): void
    {
        $workspace = InstitutionWorkspace::create([
            'institution_id' => $this->institution->id,
            'scope_type' => 'study_program',
            'scope_id' => $this->prodiA->id,
            'name' => 'WS Prodi A',
            'access_mode' => 'hierarchical',
            'created_by' => $this->adminProdiA->id,
        ]);

        $response = $this->actingAs($this->adminProdiA)->post(route('workspace-institusi.upload', $workspace), [
            'files' => [\Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('institution_workspace_files', [
            'institution_workspace_id' => $workspace->id,
            'uploaded_by' => $this->adminProdiA->id,
            'original_name' => 'doc.pdf',
        ]);
    }

    public function test_dosen_cannot_upload_but_can_download(): void
    {
        // Dosen tidak bisa upload (canManage false).
        $workspace = InstitutionWorkspace::create([
            'institution_id' => $this->institution->id,
            'scope_type' => 'study_program',
            'scope_id' => $this->prodiA->id,
            'name' => 'WS Prodi A',
            'access_mode' => 'hierarchical',
            'created_by' => $this->adminProdiA->id,
        ]);

        $response = $this->actingAs($this->dosenA)->post(route('workspace-institusi.upload', $workspace), [
            'files' => [\Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
        ]);
        $response->assertForbidden();
    }
}