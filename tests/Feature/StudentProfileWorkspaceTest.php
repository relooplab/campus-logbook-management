<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\MahasiswaTa;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentProfileWorkspaceTest extends TestCase
{
    use DatabaseTransactions;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'mahasiswa', 'guard_name' => 'web']);
        $this->student = User::create([
            'name' => 'Profil Mahasiswa Uji',
            'email' => 'profile-'.uniqid().'@example.test',
            'password' => bcrypt('secret123'),
            'registration_status' => 'active',
            'nim' => 'NIM-'.uniqid(),
            'whatsapp' => '628123456789',
        ]);
        $this->student->assignRole('mahasiswa');
    }

    public function test_student_workspace_renders_existing_editable_fields_and_collapsed_affiliation(): void
    {
        $this->actingAs($this->student)->get(route('profile.index'))
            ->assertOk()
            ->assertSee('Profil Mahasiswa')
            ->assertSee('profile-workspace-grid')
            ->assertSee('Informasi Profil')
            ->assertSee('Informasi Pribadi')
            ->assertSee('Informasi Akademik')
            ->assertSee('Keamanan Akun')
            ->assertSee('Belum ada program tugas akhir atau kerja praktik.')
            ->assertSee('id="kartu-afiliasi" class="mt-4 border-t border-border pt-4 hidden"', false)
            ->assertSee('name="nim" required', false)
            ->assertSee('form="mahasiswa-profile-form"', false)
            ->assertSee(route('profile.affiliation-mahasiswa.update'))
            ->assertSee(route('profile.email'))
            ->assertSee(route('profile.password'));
    }

    public function test_affiliation_summary_uses_saved_directory_and_editor_reopens_for_errors(): void
    {
        $university = University::create(['name' => 'Universitas Workspace Uji']);
        $faculty = Faculty::create(['name' => 'Fakultas Workspace Uji', 'university_id' => $university->id]);
        $department = Department::create(['name' => 'Departemen Workspace Uji', 'faculty_id' => $faculty->id]);
        $prodi = StudyProgram::create(['name' => 'Program Workspace Uji', 'department_id' => $department->id, 'code' => '998877']);

        $this->actingAs($this->student)->post(route('profile.affiliation-mahasiswa.update'), [
            'university_id' => $university->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'study_program_id' => $prodi->id,
        ])->assertRedirect(route('profile.index'));

        $this->actingAs($this->student)->get(route('profile.index'))
            ->assertOk()
            ->assertSee($university->name)
            ->assertSee($faculty->name)
            ->assertSee($department->name)
            ->assertSee($prodi->name)
            ->assertSee('id="kartu-afiliasi" class="mt-4 border-t border-border pt-4 hidden"', false);

        $this->actingAs($this->student)->from(route('profile.index'))
            ->post(route('profile.affiliation-mahasiswa.update'), [
                'university_id' => $university->id,
                'faculty_id' => $faculty->id,
                'department_id' => $department->id,
            ])->assertSessionHasErrors('study_program_id');

        $this->actingAs($this->student)->get(route('profile.index'))
            ->assertSee('id="affiliation-toggle" aria-controls="kartu-afiliasi" aria-expanded="true"', false)
            ->assertSee('id="kartu-afiliasi" class="mt-4 border-t border-border pt-4 "', false);
    }

    public function test_student_can_update_profile_and_photo_without_changing_nim_rules(): void
    {
        Storage::fake('public');

        $this->actingAs($this->student)->put(route('profile.update'), [
            'name' => 'Nama Diperbarui',
            'nim' => 'NIM-NEW-'.uniqid(),
            'whatsapp' => '62811111111',
            'telegram' => '@baru',
            'linkedin' => 'https://linkedin.com/in/student',
            'photo' => UploadedFile::fake()->image('avatar.png'),
        ])->assertRedirect(route('dashboard'))->assertSessionHas('success');

        $fresh = $this->student->fresh();
        $this->assertSame('Nama Diperbarui', $fresh->name);
        $this->assertStringStartsWith('NIM-NEW-', $fresh->nim);
        $this->assertSame('@baru', $fresh->telegram);
        $this->assertSame('https://linkedin.com/in/student', $fresh->linkedin);
        Storage::disk('public')->assertExists($fresh->profile_photo_path);
    }

    public function test_academic_card_keeps_ta_and_kp_forms_and_real_statuses(): void
    {
        $ta = MahasiswaTa::create([
            'user_id' => $this->student->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
            'fase' => 'proposal',
        ]);
        $kp = MahasiswaTa::create([
            'user_id' => $this->student->id,
            'jenis' => MahasiswaTa::JENIS_KP,
            'status_ta' => MahasiswaTa::STATUS_TAMAT,
            'fase' => 'selesai',
        ]);

        $this->actingAs($this->student)->get(route('profile.index'))
            ->assertOk()
            ->assertSee('Status Akademik')
            ->assertSee('Belum diisi')
            ->assertSee('Aktif')
            ->assertSee('Selesai')
            ->assertSee(route('profile.program', $ta))
            ->assertSee(route('profile.program', $kp));

        $this->actingAs($this->student)->put(route('profile.program', $ta), [
            'judul_ta' => 'Judul Akademik Uji',
        ])->assertSessionHas('success');
        $this->actingAs($this->student)->put(route('profile.program', $kp), [
            'tempat_kp' => 'Tempat KP Uji',
        ])->assertSessionHas('success');

        $this->assertSame('Judul Akademik Uji', $ta->fresh()->judul_ta);
        $this->assertSame('Tempat KP Uji', $kp->fresh()->tempat_kp);
    }

    public function test_password_workflow_keeps_existing_six_character_minimum(): void
    {
        $this->actingAs($this->student)->put(route('profile.password'), [
            'current_password' => 'secret123',
            'password' => 'newpass',
            'password_confirmation' => 'newpass',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('newpass', $this->student->fresh()->password));
    }
}
