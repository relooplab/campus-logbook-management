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
use App\Models\UserPlanOverride;
use App\Services\OrganizationalDirectoryService;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

/**
 * Verifikasi kuota storage langsung per institusi (override pool).
 *   - NULL = fallback ke subscription (backward-compatible).
 *   - terisi = override pool institusi.
 */
class InstitutionQuotaTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private Institution $institution;
    private University $univ;
    private Faculty $faculty;
    private \App\Models\Department $dept;
    private StudyProgram $prodi;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = Institution::create([
            'app_name' => 'Kuota Test',
            'institution_name' => 'Universitas Kuota Test',
            'email' => 'kuota@test.com',
        ]);

        $svc = app(OrganizationalDirectoryService::class);
        $this->univ = $svc->findOrCreateUniversity('Universitas Kuota Test');
        $this->faculty = $svc->findOrCreateFaculty($this->univ, 'Fakultas Kuota');
        $this->dept = $svc->findOrCreateDepartment($this->faculty, 'Departemen Kuota');
        $this->prodi = $svc->findOrCreateStudyProgram($this->dept, 'S1 Kuota');

        $this->plan = Plan::create([
            'name' => 'kuota_plan_'.uniqid(),
            'label' => 'Plan Kuota',
            'price' => 100000,
            'period' => 'monthly',
            'features' => ['storage_mb' => 3072, 'export' => true, 'import' => true],
            'is_active' => true,
        ]);

        // Langganan aktif di prodi -> pool institusi = 3072 MB dari subscription.
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'plan_id' => $this->plan->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        // Dosen terafiliasi di institusi & prodi.
        $svc->attachUserToUniversity($this->dosen, $this->univ, $this->faculty, $this->dept, $this->prodi, true);
        $this->dosen->update(['institution_id' => $this->institution->id]);
    }

    private function systemAdmin(): User
    {
        Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);
        $uid = uniqid();
        $sys = User::create([
            'name' => 'Sys Admin Quota', 'email' => "sys-q-{$uid}@audit.test",
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => "SYSQ-{$uid}", 'whatsapp' => '628',
        ]);
        $sys->assignRole('system_admin');
        return $sys;
    }

    public function test_falls_back_to_subscription_when_override_null(): void
    {
        $this->assertNull($this->institution->fresh()->storage_limit_mb);
        $this->assertSame(3072, Feature::institutionStorageLimitMb((int) $this->institution->id));
    }

    public function test_override_takes_priority(): void
    {
        $this->institution->update(['storage_limit_mb' => 5120]);
        Institution::flush($this->institution->id);

        $this->assertSame(5120, Feature::institutionStorageLimitMb((int) $this->institution->id));
    }

    public function test_override_zero_falls_back_to_subscription(): void
    {
        $this->institution->update(['storage_limit_mb' => 0]);
        Institution::flush($this->institution->id);

        // 0 = auto (ikuti subscription).
        $this->assertSame(3072, Feature::institutionStorageLimitMb((int) $this->institution->id));
    }

    public function test_override_propagates_to_user_storage_limit(): void
    {
        $this->institution->update(['storage_limit_mb' => 1024]);
        Institution::flush($this->institution->id);
        $this->dosen->update(['institution_storage_limit_mb' => null]);

        // Tanpa override per-user -> kuota = pool override (1024).
        $this->assertSame(1024, Feature::storageLimitMb($this->dosen));

        // Dengan cap per-user -> min(pool, per-user).
        $this->dosen->update(['institution_storage_limit_mb' => 256]);
        $this->assertSame(256, Feature::storageLimitMb($this->dosen));
    }

    public function test_system_admin_can_view_quotas_page(): void
    {
        $response = $this->actingAs($this->systemAdmin())->get(route('admin.system.institution-quotas'));
        $response->assertOk();
        $response->assertSee('Kuota Storage Institusi');
        $response->assertSee('Universitas Kuota Test');
    }

    public function test_institution_admin_cannot_access_quotas_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.system.institution-quotas'));
        $response->assertForbidden();
    }

    public function test_system_admin_can_set_institution_quota(): void
    {
        $response = $this->actingAs($this->systemAdmin())
            ->post(route('admin.system.institution-quotas.update', $this->institution), [
                'storage_limit_mb' => 2048,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame(2048, (int) $this->institution->fresh()->storage_limit_mb);
    }

    public function test_set_zero_clears_override(): void
    {
        $this->institution->update(['storage_limit_mb' => 5000]);
        Institution::flush($this->institution->id);

        $this->actingAs($this->systemAdmin())
            ->post(route('admin.system.institution-quotas.update', $this->institution), [
                'storage_limit_mb' => 0,
            ]);

        $this->assertNull($this->institution->fresh()->storage_limit_mb, '0/auto harus menghapus override.');
    }

    public function test_institution_admin_cannot_update_quota(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.system.institution-quotas.update', $this->institution), [
                'storage_limit_mb' => 9999,
            ]);
        $response->assertForbidden();
    }
}
