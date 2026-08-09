<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Alur afiliasi mahasiswa: wajib isi sampai tingkat prodi (dari direktori yang ada),
 * dan menjadi filter pencarian dosen pada langkah "Pilih Dosen".
 */
class MahasiswaAffiliationTest extends TestCase
{
    use DatabaseTransactions;

    private User $dosenA;
    private User $dosenB;
    private User $mhs;

    private University $univ1;
    private University $univ2;
    private Faculty $fac1;
    private Department $dept1;
    private StudyProgram $prodi1;
    private Faculty $fac2;
    private Department $dept2;
    private StudyProgram $prodi2;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'dosen', 'mahasiswa'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->dosenA = User::create([
            'name' => 'Dosen Univ A', 'email' => 'dosenA@aff.test', 'password' => bcrypt('x'),
            'registration_status' => 'active', 'identifier' => 'NIDN-A', 'nidn' => 'NIDN-A', 'whatsapp' => '6281',
        ]);
        $this->dosenA->assignRole('dosen');

        $this->dosenB = User::create([
            'name' => 'Dosen Univ B', 'email' => 'dosenB@aff.test', 'password' => bcrypt('x'),
            'registration_status' => 'active', 'identifier' => 'NIDN-B', 'nidn' => 'NIDN-B', 'whatsapp' => '6282',
        ]);
        $this->dosenB->assignRole('dosen');

        $this->mhs = User::create([
            'name' => 'Mahasiswa Afiliasi', 'email' => 'mhs@aff.test', 'password' => bcrypt('x'),
            'registration_status' => 'active', 'identifier' => 'NIM-AFF', 'whatsapp' => '6283',
        ]);
        $this->mhs->assignRole('mahasiswa');

        // Direktori universitas A dan B.
        $this->univ1 = University::create(['name' => 'Universitas A']);
        $this->fac1 = Faculty::create(['university_id' => $this->univ1->id, 'name' => 'Fakultas Teknik A']);
        $this->dept1 = Department::create(['faculty_id' => $this->fac1->id, 'name' => 'Departemen Informatika A']);
        $this->prodi1 = StudyProgram::create(['department_id' => $this->dept1->id, 'name' => 'S1 Informatika A', 'code' => '55201']);

        $this->univ2 = University::create(['name' => 'Universitas B']);
        $this->fac2 = Faculty::create(['university_id' => $this->univ2->id, 'name' => 'Fakultas Ekonomi B']);
        $this->dept2 = Department::create(['faculty_id' => $this->fac2->id, 'name' => 'Departemen Manajemen B']);
        $this->prodi2 = StudyProgram::create(['department_id' => $this->dept2->id, 'name' => 'S1 Manajemen B', 'code' => '61201']);

        $svc = app(OrganizationalDirectoryService::class);
        $svc->attachUserToUniversity($this->dosenA, $this->univ1, $this->fac1, $this->dept1, $this->prodi1, isPrimary: true);
        $svc->attachUserToUniversity($this->dosenB, $this->univ2, $this->fac2, $this->dept2, $this->prodi2, isPrimary: true);
    }

    private function saveAffiliation(User $mhs, University $univ, Faculty $fac, Department $dept, StudyProgram $prodi)
    {
        return $this->actingAs($mhs)->post(route('profile.affiliation-mahasiswa.update'), [
            'university_id' => $univ->id,
            'faculty_id' => $fac->id,
            'department_id' => $dept->id,
            'study_program_id' => $prodi->id,
        ]);
    }

    public function test_select_dosen_redirects_to_profile_when_no_affiliation(): void
    {
        $this->actingAs($this->mhs)
            ->get(route('profile.select-dosen'))
            ->assertRedirect(route('profile.index'));
    }

    public function test_mahasiswa_can_save_affiliation_down_to_prodi(): void
    {
        $r = $this->saveAffiliation($this->mhs, $this->univ1, $this->fac1, $this->dept1, $this->prodi1);
        $r->assertRedirect(route('profile.index'));

        $this->assertDatabaseHas('user_university', [
            'user_id' => $this->mhs->id,
            'university_id' => $this->univ1->id,
            'faculty_id' => $this->fac1->id,
            'department_id' => $this->dept1->id,
            'study_program_id' => $this->prodi1->id,
            'is_primary' => 1,
            'status' => 'active',
        ]);
    }

    public function test_save_affiliation_rejects_inconsistent_hierarchy(): void
    {
        // prodi milik universitas B dipasang bersama universitas A → ditolak.
        $r = $this->actingAs($this->mhs)->post(route('profile.affiliation-mahasiswa.update'), [
            'university_id' => $this->univ1->id,
            'faculty_id' => $this->fac1->id,
            'department_id' => $this->dept1->id,
            'study_program_id' => $this->prodi2->id,
        ]);
        $r->assertStatus(422);
        $this->assertDatabaseMissing('user_university', ['user_id' => $this->mhs->id]);
    }

    public function test_select_dosen_filters_by_university(): void
    {
        $this->saveAffiliation($this->mhs, $this->univ1, $this->fac1, $this->dept1, $this->prodi1);

        $this->actingAs($this->mhs)
            ->get(route('profile.select-dosen'))
            ->assertOk()
            ->assertSee('Universitas A')
            ->assertSee('Dosen Univ A')
            ->assertDontSee('Dosen Univ B');
    }

    public function test_store_dosen_rejects_dosen_from_other_university(): void
    {
        $this->saveAffiliation($this->mhs, $this->univ1, $this->fac1, $this->dept1, $this->prodi1);

        $this->actingAs($this->mhs)->post(route('profile.store-dosen'), [
            'jenis' => 'ta',
            'fase' => 'proposal',
            'pembimbing_1_id' => $this->dosenB->id,
        ])->assertStatus(422);
    }

    public function test_store_dosen_accepts_dosen_from_same_university(): void
    {
        $this->saveAffiliation($this->mhs, $this->univ1, $this->fac1, $this->dept1, $this->prodi1);

        $this->actingAs($this->mhs)->post(route('profile.store-dosen'), [
            'jenis' => 'ta',
            'fase' => 'proposal',
            'pembimbing_1_id' => $this->dosenA->id,
        ])->assertRedirect(route('dashboard'));
    }

    public function test_store_dosen_rejects_duplicate_dosen(): void
    {
        $this->saveAffiliation($this->mhs, $this->univ1, $this->fac1, $this->dept1, $this->prodi1);

        // Dosen yang sama dipilih di dua peran → ditolak (422).
        $this->actingAs($this->mhs)->post(route('profile.store-dosen'), [
            'jenis' => 'ta',
            'fase' => 'proposal',
            'pembimbing_1_id' => $this->dosenA->id,
            'penguji_1_id' => $this->dosenA->id,
        ])->assertStatus(422);
    }
}
