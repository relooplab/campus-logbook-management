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
            'study_program_code' => '55201',
        ]);

        $response->assertRedirect(route('verification.notice'));

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
}