<?php

namespace Tests\Feature;

use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GlobalSearchStudentLinkTest extends TestCase
{
    use DatabaseTransactions;

    private function account(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $suffix = uniqid();
        $user = User::create([
            'name' => 'Search '.$suffix, 'email' => $suffix.'@search.test',
            'password' => bcrypt('password'), 'registration_status' => 'active',
            'nim' => $role === 'mahasiswa' ? 'NIM'.$suffix : null,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function program(User $student, User $lecturer, string $jenis = 'ta'): MahasiswaTa
    {
        return MahasiswaTa::create([
            'user_id' => $student->id, 'jenis' => $jenis, 'pembimbing_1_id' => $lecturer->id,
            'fase' => $jenis === 'kp' ? 'laporan' : 'proposal',
            'status_ta' => 'aktif', 'target_sesi' => 7,
        ]);
    }

    public function test_ta_and_kp_search_results_open_program_details_by_name_and_nim(): void
    {
        foreach (['ta', 'kp'] as $jenis) {
            $student = $this->account('mahasiswa');
            $supervisor = $this->account('dosen');
            $examiner = $this->account('dosen');
            $program = $this->program($student, $supervisor, $jenis);
            $program->update(['penguji_1_id' => $examiner->id]);
            $url = route($jenis === 'kp' ? 'mahasiswa-kp.show' : 'mahasiswa-ta.show', $program);
            foreach ([$supervisor, $examiner, $student] as $viewer) {
                foreach ([$student->name, $student->nim] as $query) {
                    $this->actingAs($viewer)->getJson(route('global-search', ['q' => $query]))
                        ->assertOk()->assertJsonPath('users.0.id', $student->id)
                        ->assertJsonPath('users.0.url', $url);
                }
                $this->get($url)->assertOk();
            }
        }
    }

    public function test_kp_group_member_search_opens_group_program_not_profile(): void
    {
        $owner = $this->account('mahasiswa');
        $member = $this->account('mahasiswa');
        $lecturer = $this->account('dosen');
        $program = $this->program($owner, $lecturer, 'kp');
        $program->members()->attach($member->id);
        $url = route('mahasiswa-kp.show', $program);
        foreach ([$lecturer, $member] as $viewer) {
            $this->actingAs($viewer)->getJson(route('global-search', ['q' => $member->nim]))
                ->assertOk()->assertJsonPath('users.0.id', $member->id)
                ->assertJsonPath('users.0.url', $url);
            $this->get($url)->assertOk();
        }
    }

    public function test_search_only_links_accessible_programs_and_prefers_ta(): void
    {
        $student = $this->account('mahasiswa');
        $lecturer = $this->account('dosen');
        $otherLecturer = $this->account('dosen');
        $ta = $this->program($student, $otherLecturer);
        $kp = $this->program($student, $lecturer, 'kp');
        $this->actingAs($lecturer)->getJson(route('global-search', ['q' => $student->nim]))
            ->assertOk()->assertJsonPath('users.0.url', route('mahasiswa-kp.show', $kp));
        $ta->update(['penguji_1_id' => $lecturer->id]);
        $this->getJson(route('global-search', ['q' => $student->nim]))
            ->assertOk()->assertJsonPath('users.0.url', route('mahasiswa-ta.show', $ta));
    }

    public function test_lecturers_and_students_without_program_keep_profile_links(): void
    {
        $student = $this->account('mahasiswa');
        $lecturer = $this->account('dosen');
        $this->program($student, $lecturer);
        $this->actingAs($student)->getJson(route('global-search', ['q' => $lecturer->name]))
            ->assertOk()->assertJsonPath('users.0.url', route('profile.show', $lecturer));
        $noProgram = $this->account('mahasiswa');
        $admin = $this->account('admin');
        $this->actingAs($admin)->getJson(route('global-search', ['q' => $noProgram->nim]))
            ->assertOk()->assertJsonPath('users.0.url', route('profile.show', $noProgram));
    }

    public function test_unrelated_students_are_not_returned(): void
    {
        $student = $this->account('mahasiswa');
        $lecturer = $this->account('dosen');
        $outsider = $this->account('dosen');
        $this->program($student, $lecturer);
        $this->actingAs($outsider)->getJson(route('global-search', ['q' => $student->nim]))
            ->assertOk()->assertJsonCount(0, 'users');
    }
}