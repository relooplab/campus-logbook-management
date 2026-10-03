<?php

namespace Tests\Feature;

use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LecturerStudentWorkspaceTest extends TestCase
{
    use DatabaseTransactions;

    private User $lecturer;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['mahasiswa', 'dosen', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->lecturer = $this->user('dosen', 'Lecturer');
    }

    private function user(string $role, string $name): User
    {
        $id = uniqid();
        $user = User::create([
            'name' => $name.' '.$id,
            'email' => $id.'@t.test',
            'password' => bcrypt('password'),
            'registration_status' => 'active',
            'nim' => $role === 'mahasiswa' ? 'NIM'.$id : null,
            'nidn' => $role === 'dosen' ? 'NIDN'.$id : null,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function program(string $name, string $jenis, array $roles, string $fase, string $status = 'aktif'): MahasiswaTa
    {
        return MahasiswaTa::create(array_merge([
            'user_id' => $this->user('mahasiswa', $name)->id,
            'jenis' => $jenis,
            'fase' => $fase,
            'status_ta' => $status,
            'target_sesi' => 7,
        ], $roles));
    }

    public function test_student_name_is_clickable_link_to_program_detail(): void
    {
        $ta = $this->program('ClickableName', 'ta', ['pembimbing_1_id' => $this->lecturer->id], 'proposal');
        $kp = $this->program('ClickableKpName', 'kp', ['penguji_1_id' => $this->lecturer->id], 'laporan');

        $response = $this->actingAs($this->lecturer)->get(route('dosen.mahasiswa-saya'));

        $response->assertOk();
        // Desktop table: nama mahasiswa harus berupa anchor ke detail program.
        $response->assertSeeHtml('<a href="'.e(route('mahasiswa-ta.show', $ta)).'" title="Lihat profil '.e($ta->mahasiswa->name).'" class="history-student-link block truncate font-semibold">'.e($ta->mahasiswa->name).'</a>');
        $response->assertSeeHtml('<a href="'.e(route('mahasiswa-kp.show', $kp)).'" title="Lihat profil '.e($kp->mahasiswa->name).'" class="history-student-link block truncate font-semibold">'.e($kp->mahasiswa->name).'</a>');
        // Mobile card: nama juga anchor ke detail program.
        $response->assertSeeHtml('<a href="'.e(route('mahasiswa-ta.show', $ta)).'" title="Lihat profil '.e($ta->mahasiswa->name).'" class="history-student-link break-words text-sm font-semibold">'.e($ta->mahasiswa->name).'</a>');
    }

    public function test_unified_workspace_has_real_counts_multiple_roles_and_program_links(): void
    {
        $ta = $this->program('UniqueAlpha', 'ta', [
            'pembimbing_1_id' => $this->lecturer->id,
            'penguji_2_id' => $this->lecturer->id,
        ], 'proposal');
        $kp = $this->program('UniqueBeta', 'kp', ['penguji_1_id' => $this->lecturer->id], 'laporan', 'nonaktif');

        $response = $this->actingAs($this->lecturer)->get(route('dosen.mahasiswa-saya'));
        $response->assertOk()->assertViewHas('metrics', fn ($metrics) => $metrics === [
            'total' => 2, 'pembimbing' => 1, 'penguji' => 2, 'aktif' => 1, 'nonaktif' => 1,
        ])->assertViewHas('students', fn ($students) => $students->total() === 2
            && $students->firstWhere('id', $ta->id)->my_roles === ['Pembimbing 1', 'Penguji 2'])
            ->assertSee(route('mahasiswa-ta.show', $ta))
            ->assertSee(route('mahasiswa-kp.show', $kp))
            ->assertSee('Kelompokkan per Fase');
    }

    public function test_search_filters_tabs_and_grouped_distribution_share_one_query(): void
    {
        $ta = $this->program('SearchTarget', 'ta', ['pembimbing_1_id' => $this->lecturer->id], 'proposal');
        $this->program('OtherStudent', 'kp', ['penguji_1_id' => $this->lecturer->id], 'laporan');

        $this->actingAs($this->lecturer)->get(route('dosen.mahasiswa-saya', [
            'search' => $ta->mahasiswa->nim, 'program' => 'ta', 'fase' => 'ta:proposal',
            'status' => 'aktif', 'peran' => 'pembimbing', 'tab' => 'pembimbing', 'view' => 'fase',
        ]))->assertOk()->assertViewHas('students', fn ($students) => $students->total() === 1)
            ->assertViewHas('distribution', fn ($distribution) => $distribution->count() === 1 && $distribution['ta:proposal']->total === 1)
            ->assertSee('Distribusi Mahasiswa per Fase');

        $this->actingAs($this->lecturer)->get(route('dosen.mahasiswa-saya', ['tab' => 'penguji', 'program' => 'ta']))
            ->assertOk()->assertViewHas('students', fn ($students) => $students->total() === 0)
            ->assertSee('Tidak ada mahasiswa yang cocok');
    }

    public function test_supervisors_and_examiners_can_change_phase_from_workspace_and_detail(): void
    {
        $supervised = $this->program('SupervisorStudent', 'ta', ['pembimbing_2_id' => $this->lecturer->id], 'proposal');
        $examined = $this->program('ExaminerStudent', 'kp', ['penguji_1_id' => $this->lecturer->id], 'laporan');
        $examinedTa = $this->program('SecondExaminerStudent', 'ta', ['penguji_2_id' => $this->lecturer->id], 'proposal');

        $this->actingAs($this->lecturer)->get(route('dosen.mahasiswa-saya'))
            ->assertOk()->assertSee(route('mahasiswa-ta.fase', $supervised))
            ->assertSee(route('mahasiswa-kp.fase', $examined))
            ->assertSee(route('mahasiswa-ta.fase', $examinedTa));

        $this->actingAs($this->lecturer)->get(route('mahasiswa-kp.show', $examined))
            ->assertOk()->assertSee(route('mahasiswa-kp.fase', $examined));
        $this->actingAs($this->lecturer)->get(route('mahasiswa-ta.show', $examinedTa))
            ->assertOk()->assertSee(route('mahasiswa-ta.fase', $examinedTa));

        $this->actingAs($this->lecturer)->post(route('mahasiswa-kp.fase', $examined), ['fase' => 'selesai'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('selesai', $examined->fresh()->fase);

        $this->actingAs($this->lecturer)->post(route('mahasiswa-ta.fase', $examinedTa), ['fase' => 'pengumpulan_data'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('pengumpulan_data', $examinedTa->fresh()->fase);

        $this->actingAs($this->lecturer)->post(route('mahasiswa-ta.fase', $supervised), ['fase' => 'pengumpulan_data'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('pengumpulan_data', $supervised->fresh()->fase);
    }

    public function test_unrelated_lecturer_student_and_admin_cannot_change_phase(): void
    {
        $program = $this->program('UnrelatedStudent', 'ta', [], 'proposal');

        foreach ([$this->lecturer, $program->mahasiswa, $this->user('admin', 'Admin')] as $actor) {
            $this->actingAs($actor)->post(route('mahasiswa-ta.fase', $program), ['fase' => 'pengumpulan_data'])
                ->assertForbidden();
            $this->assertSame('proposal', $program->fresh()->fase);
        }
    }

    public function test_examiner_cannot_skip_finalization_or_submit_invalid_phase(): void
    {
        $program = $this->program('ExaminerFinalizationStudent', 'ta', ['penguji_1_id' => $this->lecturer->id], 'proposal');

        $this->actingAs($this->lecturer)->post(route('mahasiswa-ta.fase', $program), ['fase' => 'achievement'])
            ->assertForbidden();
        $this->actingAs($this->lecturer)->post(route('mahasiswa-ta.fase', $program), ['fase' => 'invalid'])
            ->assertSessionHasErrors('fase');
        $this->assertSame('proposal', $program->fresh()->fase);
    }

    public function test_pagination_keeps_filters_and_limits_loaded_students(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->program('PagedStudent', 'kp', ['pembimbing_1_id' => $this->lecturer->id], 'laporan');
        }

        $this->actingAs($this->lecturer)->get(route('dosen.mahasiswa-saya', ['search' => 'PagedStudent', 'page' => 2]))
            ->assertOk()->assertViewHas('students', fn ($students) => $students->total() === 21
                && $students->count() === 1 && $students->currentPage() === 2)
            ->assertSee('Menampilkan 21–21 dari 21');
    }

    public function test_students_cannot_open_lecturer_workspace(): void
    {
        $this->actingAs($this->user('mahasiswa', 'Student'))->get(route('dosen.mahasiswa-saya'))->assertForbidden();
    }
}
