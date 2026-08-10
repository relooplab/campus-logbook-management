<?php

namespace Tests\Feature;

use App\Models\AdminScope;
use App\Models\Department;
use App\Models\DirectorySubscription;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\Plan;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Fase D — Filter cakupan admin_scopes:
 * - Admin tanpa admin_scopes = DIKUNCI (tidak melihat data dosen/mahasiswa).
 * - Admin dengan admin_scopes aktif = data lintas-dosen ter-filter ke scope-nya
 *   (hierarkis: university > faculty > department > study_program).
 */
class AdminScopeFilterTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private OrganizationalDirectoryService $service;
    private Institution $institution;
    private University $univ;
    private Faculty $faculty;
    private Department $dept;
    private StudyProgram $prodiA;
    private StudyProgram $prodiB;
    private Plan $plan;
    private User $adminScoped;
    private User $adminFull;
    private User $dosenProdiA;
    private User $dosenProdiB;
    private User $mhsProdiA;
    private User $mhsProdiB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrganizationalDirectoryService::class);

        // Paksa mode institusi.
        config(['app.mode' => 'institution']);

        $this->institution = Institution::create([
            'app_name' => 'Scope Test',
            'institution_name' => 'Universitas Scope Test',
            'email' => 'scope@test.com',
        ]);

        $this->univ = $this->service->findOrCreateUniversity('Universitas Scope Test');
        $this->faculty = $this->service->findOrCreateFaculty($this->univ, 'Fakultas Teknik');
        $this->dept = $this->service->findOrCreateDepartment($this->faculty, 'Departemen Teknik Informatika');
        $this->prodiA = $this->service->findOrCreateStudyProgram($this->dept, 'S1 Teknik Informatika');
        $this->prodiB = $this->service->findOrCreateStudyProgram($this->dept, 'S1 Sistem Informasi');

        $this->plan = Plan::create([
            'name' => 'scope_plan',
            'label' => 'Scope Plan',
            'price' => 100000,
            'period' => 'monthly',
            'features' => ['storage_mb' => 10240, 'export' => true, 'import' => true],
            'is_active' => true,
        ]);

        // Langganan aktif di fakultas (meng-cover kedua prodi).
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_FACULTY,
            'scope_id' => $this->faculty->id,
            'plan_id' => $this->plan->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        // Admin full (tanpa admin_scopes) & admin scoped (hanya prodi A).
        $this->adminFull = User::create([
            'name' => 'Admin Full',
            'email' => 'admin-full@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $this->adminFull->assignRole('admin');

        $this->adminScoped = User::create([
            'name' => 'Admin Scoped',
            'email' => 'admin-scoped@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $this->adminScoped->assignRole('admin');

        AdminScope::create([
            'user_id' => $this->adminScoped->id,
            'institution_id' => $this->institution->id,
            'scope_type' => AdminScope::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodiA->id,
            'granted_by' => $this->adminFull->id,
            'status' => AdminScope::STATUS_ACTIVE,
        ]);

        // Dosen & mahasiswa di prodi A & B.
        $this->dosenProdiA = User::create([
            'name' => 'Dosen Prodi A',
            'email' => 'dosen-prodi-a@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $this->dosenProdiA->assignRole('dosen');
        $this->service->attachUserToUniversity($this->dosenProdiA, $this->univ, $this->faculty, $this->dept, $this->prodiA, true);

        $this->dosenProdiB = User::create([
            'name' => 'Dosen Prodi B',
            'email' => 'dosen-prodi-b@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $this->dosenProdiB->assignRole('dosen');
        $this->service->attachUserToUniversity($this->dosenProdiB, $this->univ, $this->faculty, $this->dept, $this->prodiB, true);

        $this->mhsProdiA = User::create([
            'name' => 'Mhs Prodi A',
            'email' => 'mhs-prodi-a@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $this->mhsProdiA->assignRole('mahasiswa');
        $this->service->attachUserToUniversity($this->mhsProdiA, $this->univ, $this->faculty, $this->dept, $this->prodiA, true);

        $this->mhsProdiB = User::create([
            'name' => 'Mhs Prodi B',
            'email' => 'mhs-prodi-b@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institution->id,
        ]);
        $this->mhsProdiB->assignRole('mahasiswa');
        $this->service->attachUserToUniversity($this->mhsProdiB, $this->univ, $this->faculty, $this->dept, $this->prodiB, true);
    }

    protected function tearDown(): void
    {
        config(['app.mode' => 'individual']);
        parent::tearDown();
    }

    public function test_admin_tanpa_scope_dikunci_tidak_melihat_user(): void
    {
        // Admin non-system tanpa admin_scopes DIKUNCI — tidak melihat siapa pun.
        $response = $this->actingAs($this->adminFull)->get(route('admin.users'));

        $response->assertOk();
        $response->assertDontSee('Dosen Prodi A');
        $response->assertDontSee('Dosen Prodi B');
        $response->assertDontSee('Mhs Prodi A');
        $response->assertDontSee('Mhs Prodi B');
    }

    public function test_admin_dengan_scope_hanya_melihat_user_di_scope_nya(): void
    {
        $response = $this->actingAs($this->adminScoped)->get(route('admin.users'));

        $response->assertOk();
        $response->assertSee('Dosen Prodi A');
        $response->assertSee('Mhs Prodi A');
        $response->assertDontSee('Dosen Prodi B');
        $response->assertDontSee('Mhs Prodi B');
    }

    public function test_admin_dengan_scope_tidak_bisa_hapus_user_di_luar_scope(): void
    {
        $response = $this->actingAs($this->adminScoped)
            ->delete(route('admin.users.destroy', $this->dosenProdiB));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Tidak dapat mengelola user dari institusi lain.');

        $this->assertDatabaseHas('users', ['id' => $this->dosenProdiB->id]);
    }

    public function test_admin_dengan_scope_bisa_hapus_user_di_dalam_scope(): void
    {
        $response = $this->actingAs($this->adminScoped)
            ->delete(route('admin.users.destroy', $this->dosenProdiA));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $this->dosenProdiA->id]);
    }

    public function test_admin_tanpa_scope_dikunci_tidak_melihat_ta(): void
    {
        // Buat TA untuk mhs prodi A & B.
        $taA = MahasiswaTa::create([
            'institution_id' => $this->institution->id,
            'user_id' => $this->mhsProdiA->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosenProdiA->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
        ]);
        $taB = MahasiswaTa::create([
            'institution_id' => $this->institution->id,
            'user_id' => $this->mhsProdiB->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosenProdiB->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
        ]);

        // Admin non-system tanpa admin_scopes DIKUNCI — tidak melihat TA siapa pun.
        $response = $this->actingAs($this->adminFull)->get(route('admin.tas'));

        $response->assertOk();
        $response->assertDontSee('Mhs Prodi A');
        $response->assertDontSee('Mhs Prodi B');
    }

    public function test_admin_dengan_scope_hanya_melihat_ta_di_scope_nya(): void
    {
        // Buat TA untuk mhs prodi A & B.
        $taA = MahasiswaTa::create([
            'institution_id' => $this->institution->id,
            'user_id' => $this->mhsProdiA->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosenProdiA->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
        ]);
        $taB = MahasiswaTa::create([
            'institution_id' => $this->institution->id,
            'user_id' => $this->mhsProdiB->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosenProdiB->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
        ]);

        $response = $this->actingAs($this->adminScoped)->get(route('admin.tas'));

        $response->assertOk();
        $response->assertSee('Mhs Prodi A');
        $response->assertDontSee('Mhs Prodi B');
    }

    private function grantScopePerms(\App\Models\User $admin, array $perms): void
    {
        foreach ($perms as $p) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $admin->givePermissionTo($p);
        }
    }

    private function makeTa(\App\Models\User $mhs, \App\Models\User $dosen): MahasiswaTa
    {
        return MahasiswaTa::create([
            'institution_id' => $this->institution->id,
            'user_id' => $mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $dosen->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
        ]);
    }

    public function test_admin_dengan_scope_tidak_bisa_update_ta_luar_scope(): void
    {
        $this->grantScopePerms($this->adminScoped, ['admin.tas']);
        $taB = $this->makeTa($this->mhsProdiB, $this->dosenProdiB);

        $this->actingAs($this->adminScoped)->put(route('admin.tas.update', $taB), [
            'target_sesi' => 9, 'judul_ta' => 'Disusupi',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('mahasiswa_ta', ['id' => $taB->id, 'judul_ta' => null]);
    }

    public function test_admin_dengan_scope_tidak_bisa_update_status_ta_luar_scope(): void
    {
        $this->grantScopePerms($this->adminScoped, ['admin.tas']);
        $taB = $this->makeTa($this->mhsProdiB, $this->dosenProdiB);

        $this->actingAs($this->adminScoped)->post(route('admin.tas.status', $taB), [
            'status_ta' => MahasiswaTa::STATUS_TAMAT,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('mahasiswa_ta', ['id' => $taB->id, 'status_ta' => MahasiswaTa::STATUS_AKTIF]);
    }

    public function test_admin_dengan_scope_tidak_bisa_buat_sidang_untuk_ta_luar_scope(): void
    {
        $this->grantScopePerms($this->adminScoped, ['admin.sidangs']);
        $taB = $this->makeTa($this->mhsProdiB, $this->dosenProdiB);

        $this->actingAs($this->adminScoped)->post(route('admin.sidangs.store'), [
            'mahasiswa_ta_id' => $taB->id,
            'penguji_id' => $this->dosenProdiB->id,
            'jenis' => \App\Models\Sidang::JENIS_SIDANG,
            'tanggal' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('sidangs', ['mahasiswa_ta_id' => $taB->id]);
    }

    public function test_admin_dengan_scope_tidak_bisa_buat_ta_mahasiswa_luar_scope(): void
    {
        $this->grantScopePerms($this->adminScoped, ['admin.tas']);

        $this->actingAs($this->adminScoped)->post(route('admin.tas.store'), [
            'user_id' => $this->mhsProdiB->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'target_sesi' => 7,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('mahasiswa_ta', ['user_id' => $this->mhsProdiB->id]);
    }

    public function test_admin_dengan_scope_bisa_kelola_ta_di_scope(): void
    {
        $this->grantScopePerms($this->adminScoped, ['admin.tas']);
        $taA = $this->makeTa($this->mhsProdiA, $this->dosenProdiA);

        $this->actingAs($this->adminScoped)->put(route('admin.tas.update', $taA), [
            'target_sesi' => 9, 'judul_ta' => 'Dalam Scope',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('mahasiswa_ta', ['id' => $taA->id, 'judul_ta' => 'Dalam Scope']);
    }

    public function test_admin_dengan_scope_tidak_bisa_bulk_delete_entri_luar_scope(): void
    {
        $this->grantScopePerms($this->adminScoped, ['admin.bulk-review']);
        $taB = $this->makeTa($this->mhsProdiB, $this->dosenProdiB);
        $entry = LogbookEntry::create([
            'mahasiswa_ta_id' => $taB->id,
            'dosen_id' => $this->dosenProdiB->id,
            'jenis' => LogbookEntry::JENIS_LOGBOOK,
            'sesi_ke' => 1,
            'tanggal_bimbingan' => now(),
            'topik' => 'Entri Prodi B',
            'progres_kendala' => 'x',
            'status' => LogbookEntry::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->adminScoped)->post(route('admin.bulk'), [
            'ids' => [$entry->id],
            'action' => 'delete',
        ]);

        $this->assertDatabaseHas('logbook_entries', ['id' => $entry->id]);
    }
}