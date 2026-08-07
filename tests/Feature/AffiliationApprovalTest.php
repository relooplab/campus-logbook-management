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
 * Verifikasi fitur ubah afiliasi dosen + gate persetujuan:
 * - Node berlangganan → afiliasi `pending`; akses Workspace Institusi baru ada
 *   setelah admin level terendah menyetujui.
 * - Node tidak berlangganan → afiliasi langsung `active`.
 * - Revoke → akses Workspace Institusi otomatis hilang.
 * - Admin di luar scope tidak bisa menyetujui.
 */
class AffiliationApprovalTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private OrganizationalDirectoryService $service;
    private Institution $institution;
    private University $univ;
    private Faculty $faculty;
    private Department $dept;
    private StudyProgram $prodi;
    private Plan $plan;
    private User $adminProdi;
    private User $lecturer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.mode' => 'institution']);
        $this->service = app(OrganizationalDirectoryService::class);

        $this->institution = Institution::create([
            'app_name' => 'Afiliasi Test',
            'institution_name' => 'Universitas Afiliasi Test',
            'email' => 'aff@test.com',
        ]);

        $this->univ = $this->service->findOrCreateUniversity('Universitas Afiliasi Test');
        $this->faculty = $this->service->findOrCreateFaculty($this->univ, 'Fakultas Teknik');
        $this->dept = $this->service->findOrCreateDepartment($this->faculty, 'Departemen Teknik Informatika');
        $this->prodi = $this->service->findOrCreateStudyProgram($this->dept, 'Prodi Berlangganan');

        $this->plan = Plan::create([
            'name' => 'aff_plan',
            'label' => 'Aff Plan',
            'price' => 100000,
            'period' => 'monthly',
            'features' => ['storage_mb' => 10240, 'export' => true, 'import' => true],
            'is_active' => true,
        ]);

        // Langganan aktif di universitas → meng-cover prodi via leluhur.
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_UNIVERSITY,
            'scope_id' => $this->univ->id,
            'plan_id' => $this->plan->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        $this->adminProdi = User::create([
            'name' => 'Admin Prodi',
            'email' => 'aff-admin@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $this->adminProdi->assignRole('admin');
        AdminScope::create([
            'user_id' => $this->adminProdi->id,
            'institution_id' => $this->institution->id,
            'scope_type' => AdminScope::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'status' => AdminScope::STATUS_ACTIVE,
        ]);

        $this->lecturer = User::create([
            'name' => 'Dosen Afiliasi',
            'email' => 'aff-dosen@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $this->lecturer->assignRole('dosen');
    }

    protected function tearDown(): void
    {
        config(['app.mode' => 'individual']);
        parent::tearDown();
    }

    private function makeWorkspace(): InstitutionWorkspace
    {
        return InstitutionWorkspace::create([
            'institution_id' => $this->institution->id,
            'scope_type' => 'study_program',
            'scope_id' => $this->prodi->id,
            'name' => 'WS Prodi',
            'access_mode' => 'hierarchical',
            'created_by' => $this->adminProdi->id,
        ]);
    }

    public function test_subscribed_prodi_needs_approval_before_workspace_access(): void
    {
        $workspace = $this->makeWorkspace();

        // Dosen mengubah afiliasi ke prodi berlangganan.
        $this->actingAs($this->lecturer)->post(route('profile.affiliation.update'), [
            'university_name' => $this->univ->name,
            'faculty_name' => $this->faculty->name,
            'department_name' => $this->dept->name,
            'study_program_name' => $this->prodi->name,
        ])->assertRedirect(route('profile.affiliation'));

        // Status pending → BELUM ada akses workspace (bocor tercegah).
        $this->lecturer->refresh();
        $pivot = $this->lecturer->universities()->where('university_id', $this->univ->id)->first()->pivot;
        $this->assertSame(OrganizationalDirectoryService::STATUS_PENDING, $pivot->status);
        $this->assertFalse($workspace->isAccessibleBy($this->lecturer));

        // HTTP: dosen masih ditolak melihat workspace.
        $this->actingAs($this->lecturer)
            ->get(route('workspace-institusi.show', $workspace))
            ->assertForbidden();

        // Admin menyetujui.
        $this->actingAs($this->adminProdi)->post(route('affiliation-approval.approve', [$this->lecturer, $this->univ]))
            ->assertRedirect();

        $this->lecturer->refresh();
        $pivot = $this->lecturer->universities()->where('university_id', $this->univ->id)->first()->pivot;
        $this->assertSame(OrganizationalDirectoryService::STATUS_ACTIVE, $pivot->status);
        $this->assertSame((int) $pivot->is_primary, 1);
        $this->assertSame($this->adminProdi->id, $pivot->approved_by);
        $this->assertTrue($workspace->isAccessibleBy($this->lecturer));
    }

    public function test_non_subscribed_prodi_affiliation_is_active_immediately(): void
    {
        $univB = $this->service->findOrCreateUniversity('Universitas Tanpa Langganan');
        $facultyB = $this->service->findOrCreateFaculty($univB, 'Fakultas B');
        $deptB = $this->service->findOrCreateDepartment($facultyB, 'Departemen B');
        $prodiB = $this->service->findOrCreateStudyProgram($deptB, 'Prodi B');

        $this->actingAs($this->lecturer)->post(route('profile.affiliation.update'), [
            'university_name' => $univB->name,
            'faculty_name' => $facultyB->name,
            'department_name' => $deptB->name,
            'study_program_name' => $prodiB->name,
        ])->assertRedirect(route('profile.affiliation'));

        $this->lecturer->refresh();
        $pivot = $this->lecturer->universities()->where('university_id', $univB->id)->first()->pivot;
        $this->assertSame(OrganizationalDirectoryService::STATUS_ACTIVE, $pivot->status);
        $this->assertNull($pivot->approved_by);
    }

    public function test_revoke_removes_workspace_access(): void
    {
        $workspace = $this->makeWorkspace();

        // Alur penuh: ubah → pending → approve → akses ada.
        $this->actingAs($this->lecturer)->post(route('profile.affiliation.update'), [
            'university_name' => $this->univ->name,
            'faculty_name' => $this->faculty->name,
            'department_name' => $this->dept->name,
            'study_program_name' => $this->prodi->name,
        ]);
        $this->actingAs($this->adminProdi)->post(route('affiliation-approval.approve', [$this->lecturer, $this->univ]));
        $this->lecturer->refresh();
        $this->assertTrue($workspace->isAccessibleBy($this->lecturer));

        // Revoke → akses otomatis hilang.
        $this->actingAs($this->lecturer)->post(route('profile.affiliation.revoke', $this->univ))
            ->assertRedirect(route('profile.affiliation'));

        $this->lecturer->refresh();
        $pivot = $this->lecturer->universities()->where('university_id', $this->univ->id)->first()->pivot;
        $this->assertSame(OrganizationalDirectoryService::STATUS_REVOKED, $pivot->status);
        $this->assertSame((int) $pivot->is_primary, 0);
        $this->assertFalse($workspace->isAccessibleBy($this->lecturer));
    }

    public function test_admin_outside_scope_cannot_approve(): void
    {
        $prodiB = $this->service->findOrCreateStudyProgram($this->dept, 'Prodi Lain');

        // Admin di prodi lain (bukan prodi target).
        $adminLain = User::create([
            'name' => 'Admin Lain',
            'email' => 'aff-admin-lain@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $adminLain->assignRole('admin');
        AdminScope::create([
            'user_id' => $adminLain->id,
            'institution_id' => $this->institution->id,
            'scope_type' => AdminScope::SCOPE_STUDY_PROGRAM,
            'scope_id' => $prodiB->id,
            'status' => AdminScope::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->lecturer)->post(route('profile.affiliation.update'), [
            'university_name' => $this->univ->name,
            'faculty_name' => $this->faculty->name,
            'department_name' => $this->dept->name,
            'study_program_name' => $this->prodi->name,
        ]);

        // Admin di prodi lain mencoba approve → 403.
        $this->actingAs($adminLain)
            ->post(route('affiliation-approval.approve', [$this->lecturer, $this->univ]))
            ->assertForbidden();

        $this->lecturer->refresh();
        $pivot = $this->lecturer->universities()->where('university_id', $this->univ->id)->first()->pivot;
        $this->assertSame(OrganizationalDirectoryService::STATUS_PENDING, $pivot->status);
    }
}
