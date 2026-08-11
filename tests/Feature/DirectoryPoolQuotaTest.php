<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DirectorySubscription;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

/**
 * Verifikasi model baru: pool kuota institusi di-input langsung
 * (storage_limit_mb), tidak terikat plan.
 * Plan hanya untuk dosen (individu).
 */
class DirectoryPoolQuotaTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private University $univ;
    private Faculty $faculty;
    private Department $dept;
    private StudyProgram $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $svc = app(OrganizationalDirectoryService::class);
        $this->univ = $svc->findOrCreateUniversity('Univ Pool '.uniqid());
        $this->faculty = $svc->findOrCreateFaculty($this->univ, 'Fak Pool');
        $this->dept = $svc->findOrCreateDepartment($this->faculty, 'Dept Pool');
        $this->prodi = $svc->findOrCreateStudyProgram($this->dept, 'S1 Pool');

        $svc->attachUserToUniversity($this->dosen, $this->univ, $this->faculty, $this->dept, $this->prodi, true);
    }

    private function systemAdmin(): User
    {
        Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);
        $uid = uniqid();
        $s = User::create([
            'name' => 'Sys Admin Pool', 'email' => "sys-pool-{$uid}@audit.test",
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => 'SYS-'.uniqid(), 'whatsapp' => '628',
        ]);
        $s->assignRole('system_admin');
        return $s;
    }

    public function test_subscription_pool_uses_direct_storage_limit(): void
    {
        $sub = DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'storage_limit_mb' => 8192,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);
        $this->dosen->update(['institution_id' => 1]);

        $this->assertSame(8192, $sub->poolLimitMb());
        $this->assertSame(8192, Feature::institutionStorageLimitMb(1));
        $this->assertSame(8192, Feature::storageLimitMb($this->dosen));
    }

    public function test_system_admin_assigns_pool_via_subscription(): void
    {
        $response = $this->actingAs($this->systemAdmin())
            ->post(route('admin.system.directory-subscriptions.store'), [
                'scope_type' => 'study_program',
                'scope_id' => $this->prodi->id,
                'storage_limit_mb' => 5120,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $sub = DirectorySubscription::where('scope_id', $this->prodi->id)->first();
        $this->assertSame(5120, (int) $sub->storage_limit_mb);
    }

    public function test_update_subscription_pool(): void
    {
        $sub = DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'storage_limit_mb' => 1000,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
        ]);

        $this->actingAs($this->systemAdmin())
            ->put(route('admin.system.directory-subscriptions.update', $sub), [
                'storage_limit_mb' => 7777,
                'ends_at' => null,
                'status' => 'active',
            ]);

        $this->assertSame(7777, (int) $sub->fresh()->storage_limit_mb);
    }

    public function test_system_admin_can_create_and_delete_plan(): void
    {
        $sys = $this->systemAdmin();

        // Tes skalarnya positif: validasi plan dibuat (bukan cek nilai DB pas-plan).
        $response = $this->actingAs($sys)->post(route('admin.system.plans.store'), [
            'name' => 'pro_'.uniqid(),
            'label' => 'Paket Pro',
            'price' => 150000,
            'period' => 'monthly',
            'storage_mb' => 20480,
            'export' => '1',
            'import' => '1',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $plan = \App\Models\Plan::where('name', 'like', 'pro_%')->firstOrFail();
        $this->assertSame('Paket Pro', $plan->label);
        $this->assertSame(20480, $plan->storageLimitMb());

        // Hapus plan (tidak dipakai subscription apa pun).
        $del = $this->actingAs($sys)->delete(route('admin.system.plans.destroy', $plan));
        $del->assertSessionHas('success');
        $this->assertNull(\App\Models\Plan::find($plan->id));
    }

    public function test_edit_faculty_and_department_and_prodi(): void
    {
        $sys = $this->systemAdmin();

        // Edit fakultas
        $this->actingAs($sys)->put(route('admin.system.directory.faculties.update', $this->faculty), ['name' => 'Fak Rename']);
        $this->assertSame('Fak Rename', $this->faculty->fresh()->name);

        // Edit departemen
        $this->actingAs($sys)->put(route('admin.system.directory.departments.update', $this->dept), ['name' => 'Dept Rename']);
        $this->assertSame('Dept Rename', $this->dept->fresh()->name);

        // Edit prodi + kode
        $this->actingAs($sys)->put(route('admin.system.directory.study-programs.update', $this->prodi), ['name' => 'S1 Rename', 'code' => 'X']);
        $this->assertSame('S1 Rename', $this->prodi->fresh()->name);
        $this->assertSame('X', $this->prodi->fresh()->code);
    }
}
