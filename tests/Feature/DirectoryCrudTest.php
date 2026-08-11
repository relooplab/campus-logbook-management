<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

/**
 * Verifikasi halaman + endpoint CRUD struktur direktori (univ/fakultas/dept/prodi).
 */
class DirectoryCrudTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private function systemAdmin(): User
    {
        Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);
        $uid = uniqid();
        $sys = User::create([
            'name' => 'Sys Admin CRUD', 'email' => "sys-crud-{$uid}@audit.test",
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => "SYS-CRUD-{$uid}", 'whatsapp' => '628',
        ]);
        $sys->assignRole('system_admin');

        return $sys;
    }

    public function test_directory_page_loads_for_system_admin(): void
    {
        $response = $this->actingAs($this->systemAdmin())
            ->get(route('admin.system.directory'));

        $response->assertOk();
        $response->assertSee('Kelola Struktur Direktori');
    }

    public function test_non_system_admin_cannot_access_directory(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.system.directory'));

        $response->assertForbidden();
    }

    public function test_can_create_university(): void
    {
        $name = 'Universitas CRUD Test '.uniqid();

        $response = $this->actingAs($this->systemAdmin())
            ->post(route('admin.system.directory.universities.store'), [
                'name' => $name,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('universities', ['name' => $name]);
    }

    public function test_university_dedup_on_duplicate_name(): void
    {
        $name = 'Universitas Dedup '.uniqid();
        $sys = $this->systemAdmin();

        // First create
        $this->actingAs($sys)
            ->post(route('admin.system.directory.universities.store'), ['name' => $name])->assertRedirect();
        $first = University::where('name', $name)->firstOrFail();

        // Second create with same name (case insensitive) -> harusnya tidak menambah row
        $this->actingAs($sys)
            ->post(route('admin.system.directory.universities.store'), ['name' => strtoupper($name)])->assertRedirect();

        $this->assertSame(1, University::where('name', $name)->count(), 'Duplicate harus dedup, bukan insert baru.');
    }

    public function test_can_create_faculty(): void
    {
        $univ = University::create(['name' => 'Univ Fak '.uniqid()]);

        $response = $this->actingAs($this->systemAdmin())
            ->post(route('admin.system.directory.faculties.store'), [
                'university_id' => $univ->id,
                'name' => 'Fakultas CRUD Test',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('faculties', [
            'university_id' => $univ->id,
            'name' => 'Fakultas CRUD Test',
        ]);
    }

    public function test_can_create_department(): void
    {
        $univ = University::create(['name' => 'Univ Dept '.uniqid()]);
        $faculty = Faculty::create(['university_id' => $univ->id, 'name' => 'Fak Dept '.uniqid()]);

        $response = $this->actingAs($this->systemAdmin())
            ->post(route('admin.system.directory.departments.store'), [
                'faculty_id' => $faculty->id,
                'name' => 'Departemen CRUD Test',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('departments', [
            'faculty_id' => $faculty->id,
            'name' => 'Departemen CRUD Test',
        ]);
    }

    public function test_can_create_study_program(): void
    {
        $univ = University::create(['name' => 'Univ SP '.uniqid()]);
        $faculty = Faculty::create(['university_id' => $univ->id, 'name' => 'Fak SP '.uniqid()]);
        $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'Dept SP '.uniqid()]);

        $response = $this->actingAs($this->systemAdmin())
            ->post(route('admin.system.directory.study-programs.store'), [
                'department_id' => $dept->id,
                'name' => 'S1 CRUD Test',
                'code' => 'CRUD-1',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('study_programs', [
            'department_id' => $dept->id,
            'name' => 'S1 CRUD Test',
            'code' => 'CRUD-1',
        ]);
    }

    public function test_university_validation_fails_when_name_missing(): void
    {
        $response = $this->actingAs($this->systemAdmin())
            ->post(route('admin.system.directory.universities.store'), [
                'name' => '',
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_can_edit_university_name(): void
    {
        $univ = University::create(['name' => 'Univ Edit Test '.uniqid()]);
        $newName = 'Universitas Ganti Nama '.uniqid();

        $response = $this->actingAs($this->systemAdmin())
            ->put(route('admin.system.directory.universities.update', $univ), [
                'name' => $newName,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame($newName, $univ->fresh()->name);
    }

    public function test_university_edit_rejects_duplicate_name(): void
    {
        $univA = University::create(['name' => 'Univ A '.uniqid()]);
        $univB = University::create(['name' => 'Univ B '.uniqid()]);
        $nameB = $univB->name;

        $response = $this->actingAs($this->systemAdmin())
            ->put(route('admin.system.directory.universities.update', $univA), [
                'name' => $nameB,
            ]);

        $response->assertSessionHas('error');
        $this->assertSame($univA->name, $univA->fresh()->name, 'Nama tidak boleh bentrok dengan universitas lain.');
    }

    public function test_edit_page_loads(): void
    {
        $univ = University::create(['name' => 'Univ Edit Page '.uniqid()]);
        $response = $this->actingAs($this->systemAdmin())
            ->get(route('admin.system.directory.universities.edit', $univ));
        $response->assertOk();
        $response->assertSee('Edit Universitas');
        $response->assertSee($univ->name);
    }

    public function test_faculty_validation_fails_when_university_invalid(): void
    {
        $response = $this->actingAs($this->systemAdmin())
            ->post(route('admin.system.directory.faculties.store'), [
                'university_id' => 999999,
                'name' => 'Fak Invalid',
            ]);

        $response->assertSessionHasErrors('university_id');
    }
}
