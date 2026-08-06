<?php

namespace Tests\Feature;

use App\Models\University;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class OrganizationalDirectoryTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private OrganizationalDirectoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrganizationalDirectoryService::class);
    }

    public function test_dedup_university_by_name_case_insensitive(): void
    {
        $first = $this->service->findOrCreateUniversity('Universitas Indonesia');
        $second = $this->service->findOrCreateUniversity('universitas indonesia');

        // Dedup bekerja: kedua panggilan mengembalikan record yang sama.
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, University::where('name', 'Universitas Indonesia')->count());
    }

    public function test_find_or_create_full_hierarchy(): void
    {
        $university = $this->service->findOrCreateUniversity('Institut Teknologi Bandung');
        $faculty = $this->service->findOrCreateFaculty($university, 'Fakultas Teknik');
        $department = $this->service->findOrCreateDepartment($faculty, 'Departemen Teknik Informatika');
        $prodi = $this->service->findOrCreateStudyProgram($department, 'S1 Teknik Informatika', '55201');

        $this->assertNotNull($prodi->id);
        $this->assertSame('S1 Teknik Informatika', $prodi->name);
        $this->assertSame('55201', $prodi->code);
        $this->assertSame($university->id, $prodi->department->faculty->university->id);
    }

    public function test_dosen_registration_creates_university_and_links(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Dosen Baru',
            'email' => 'dosen-baru@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'dosen',
            'nidn' => '1234567890',
            'university_name' => 'Universitas Gadjah Mada',
            'faculty_name' => 'Fakultas Teknik',
            'department_name' => 'Departemen Teknik Elektro',
            'study_program_name' => 'S1 Teknik Elektro',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'dosen-baru@test.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('1234567890', $user->nidn);
        $this->assertTrue($user->hasRole('dosen'));

        $university = $user->primaryUniversity();
        $this->assertNotNull($university);
        $this->assertEquals('Universitas Gadjah Mada', $university->name);
    }

    public function test_storage_charged_to_pembimbing1_only(): void
    {
        $service = app(\App\Services\StorageUsageService::class);

        $baselineDosen = $service->totalBytes($this->dosen);
        $baselineDosen2 = $service->totalBytes($this->dosen2);

        // $this->ta sudah punya pembimbing_1 = dosen, pembimbing_2 = dosen2.
        \App\Models\WorkspaceFile::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'uploaded_by' => $this->mhs->id,
            'original_name' => 'file.pdf',
            'path' => 'workspace/test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
        ]);

        $this->assertSame($baselineDosen + 1000, $service->totalBytes($this->dosen));
        $this->assertSame($baselineDosen2, $service->totalBytes($this->dosen2));
    }

    public function test_invited_student_inherits_dosen_university(): void
    {
        // Dosen punya universitas.
        $university = $this->service->findOrCreateUniversity('Universitas Airlangga');
        $this->service->attachUserToUniversity($this->dosen, $university, isPrimary: true);

        // Dosen meng-invite mahasiswa.
        $this->actingAs($this->dosen)->post(route('approval.invite'), [
            'email' => 'mahasiswa-invite@test.com',
        ])->assertRedirect(route('approval.index'));

        $mhs = User::where('email', 'mahasiswa-invite@test.com')->first();
        $this->assertNotNull($mhs);
        $this->assertSame($university->id, $mhs->primaryUniversity()->id);
    }

    public function test_student_affiliation_replaced_when_invited_by_dosen_from_different_university(): void
    {
        // Mahasiswa self-register di universitas A (prodi A).
        $univA = $this->service->findOrCreateUniversity('Universitas A');
        $facultyA = $this->service->findOrCreateFaculty($univA, 'Fakultas A');
        $deptA = $this->service->findOrCreateDepartment($facultyA, 'Departemen A');
        $prodiA = $this->service->findOrCreateStudyProgram($deptA, 'Prodi A');

        $mhs = User::create([
            'name' => 'Mahasiswa Multi',
            'email' => 'mhs-multi@test.com',
            'password' => bcrypt('password'),
            'registration_status' => 'active',
        ]);
        $mhs->assignRole('mahasiswa');
        $this->service->attachUserToUniversity($mhs, $univA, $facultyA, $deptA, $prodiA, true);

        // Dosen dari universitas B (prodi B) meng-invite mahasiswa.
        $univB = $this->service->findOrCreateUniversity('Universitas B');
        $facultyB = $this->service->findOrCreateFaculty($univB, 'Fakultas B');
        $deptB = $this->service->findOrCreateDepartment($facultyB, 'Departemen B');
        $prodiB = $this->service->findOrCreateStudyProgram($deptB, 'Prodi B');
        $this->service->attachUserToUniversity($this->dosen, $univB, $facultyB, $deptB, $prodiB, true);

        // Dosen meng-invite mahasiswa (email sudah ada → ditolak, tapi kita
        // langsung panggil copyUniversityToStudent via approve flow).
        // Simulasi: mahasiswa memilih dosen → dosen approve.
        $ta = \App\Models\MahasiswaTa::create([
            'user_id' => $mhs->id,
            'jenis' => \App\Models\MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosen->id,
            'target_sesi' => 7,
            'status_ta' => \App\Models\MahasiswaTa::STATUS_PENDING_APPROVAL,
            'fase' => 'proposal',
        ]);

        $this->actingAs($this->dosen)->post(route('approval.approve', $ta), [
            'judul_ta' => 'Judul TA',
            'role_dosen' => 'pembimbing_1',
            'target_sesi' => 7,
        ])->assertRedirect(route('approval.index'));

        // Mahasiswa HANYA punya 1 afiliasi — universitas B (dosen), bukan A.
        $mhs->refresh();
        $this->assertSame(1, $mhs->universities()->count());
        $this->assertSame($univB->id, $mhs->primaryUniversity()->id);
        $this->assertSame($prodiB->id, $mhs->universities()->first()->pivot->study_program_id);
    }

    public function test_dosen_can_have_multiple_affiliations(): void
    {
        $univA = $this->service->findOrCreateUniversity('Universitas Multi A');
        $univB = $this->service->findOrCreateUniversity('Universitas Multi B');

        $this->service->attachUserToUniversity($this->dosen, $univA, isPrimary: true);
        $this->service->attachUserToUniversity($this->dosen, $univB, isPrimary: false);

        $this->assertSame(2, $this->dosen->universities()->count());
    }
}
