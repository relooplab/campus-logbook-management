<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DirectorySubscription;
use App\Models\Faculty;
use App\Models\Plan;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Verifikasi halaman edit + update DirectorySubscription (plan/ends_at/status).
 */
class DirectoryEditTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private University $univ;
    private Faculty $faculty;
    private Department $dept;
    private StudyProgram $prodi;
    private Plan $planA;
    private Plan $planB;

    protected function setUp(): void
    {
        parent::setUp();

        $svc = app(OrganizationalDirectoryService::class);
        $this->univ = $svc->findOrCreateUniversity('Direktori Edit Test Univ');
        $this->faculty = $svc->findOrCreateFaculty($this->univ, 'Fakultas Edit Test');
        $this->dept = $svc->findOrCreateDepartment($this->faculty, 'Departemen Edit Test');
        $this->prodi = $svc->findOrCreateStudyProgram($this->dept, 'S1 Edit Test');

        $this->planA = Plan::create([
            'name' => 'edit_test_a_'.uniqid(),
            'label' => 'Plan A Edit',
            'price' => 50000,
            'period' => 'monthly',
            'features' => ['storage_mb' => 5120, 'export' => true, 'import' => true],
            'is_active' => true,
        ]);
        $this->planB = Plan::create([
            'name' => 'edit_test_b_'.uniqid(),
            'label' => 'Plan B Edit',
            'price' => 100000,
            'period' => 'monthly',
            'features' => ['storage_mb' => 10240, 'export' => true, 'import' => true],
            'is_active' => true,
        ]);
    }

    private function systemAdmin(): User
    {
        Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);
        $uid = uniqid();
        $sys = User::create([
            'name' => 'Sys Admin Edit', 'email' => "sys-edit-{$uid}@audit.test",
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => "SYS-EDIT-{$uid}", 'whatsapp' => '628',
        ]);
        $sys->assignRole('system_admin');

        return $sys;
    }

    public function test_system_admin_can_view_edit_page(): void
    {
        $sub = DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'storage_limit_mb' => 5120,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($this->systemAdmin())
            ->get(route('admin.system.directory-subscriptions.edit', $sub));

        $response->assertOk();
        $response->assertSee('Edit Langganan Direktori');
        $response->assertSee($this->prodi->name); // scope name rendered
    }

    public function test_update_changes_pool_ends_at_and_status(): void
    {
        $sub = DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'storage_limit_mb' => 5120,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $newEnd = now()->addDays(60)->format('Y-m-d');

        $response = $this->actingAs($this->systemAdmin())
            ->put(route('admin.system.directory-subscriptions.update', $sub), [
                'storage_limit_mb' => 10240,
                'ends_at' => $newEnd,
                'status' => 'cancelled',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $sub->refresh();
        $this->assertSame(10240, (int) $sub->storage_limit_mb);
        $this->assertSame('cancelled', $sub->status);
        $this->assertNotNull($sub->ends_at);
        $this->assertSame($newEnd, $sub->ends_at->format('Y-m-d'));
    }

    public function test_update_with_invalid_pool_fails(): void
    {
        $sub = DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'storage_limit_mb' => 5120,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
        ]);

        $response = $this->actingAs($this->systemAdmin())
            ->put(route('admin.system.directory-subscriptions.update', $sub), [
                'storage_limit_mb' => 0,
                'ends_at' => null,
                'status' => 'active',
            ]);

        $response->assertSessionHasErrors('storage_limit_mb');
    }

    public function test_update_rejected_when_no_overlap_conflict(): void
    {
        // Sub di fakultas (leluhur prodi) — saat prodi di-edit, ancestor check akan gagal.
        $parentSub = DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_FACULTY,
            'scope_id' => $this->faculty->id,
            'storage_limit_mb' => 5120,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
        ]);

        // Sub di prodi (anak fakultas) — coba set status=active, harus ditolak.
        $childSub = DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'storage_limit_mb' => 5120,
            'status' => 'cancelled',
            'starts_at' => now(),
        ]);

        $response = $this->actingAs($this->systemAdmin())
            ->put(route('admin.system.directory-subscriptions.update', $childSub), [
                'storage_limit_mb' => 5120,
                'ends_at' => null,
                'status' => 'active',
            ]);

        $response->assertSessionHas('error');
        $childSub->refresh();
        $this->assertSame('cancelled', $childSub->status, 'Status tidak boleh berubah saat ada konflik no-overlap.');
    }

    public function test_non_system_admin_cannot_access_edit(): void
    {
        $sub = DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'storage_limit_mb' => 5120,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
        ]);

        // Admin biasa (role 'admin') tidak boleh akses halaman edit.
        $response = $this->actingAs($this->admin)
            ->get(route('admin.system.directory-subscriptions.edit', $sub));

        $response->assertForbidden();

        $put = $this->actingAs($this->admin)
            ->put(route('admin.system.directory-subscriptions.update', $sub), [
                'storage_limit_mb' => 5120,
                'ends_at' => null,
                'status' => 'cancelled',
            ]);

        $put->assertForbidden();
    }
}
