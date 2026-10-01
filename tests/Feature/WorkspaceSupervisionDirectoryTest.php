<?php

namespace Tests\Feature;

use App\Models\MahasiswaTa;
use App\Models\User;
use App\Models\WorkspaceFile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceSupervisionDirectoryTest extends TestCase
{
    use DatabaseTransactions;

    private User $lecturer;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['dosen', 'mahasiswa'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->lecturer = $this->newUser('dosen', 'Directory Lecturer');
    }

    private function newUser(string $role, string $name): User
    {
        $id = uniqid();
        $user = User::create([
            'name' => $name.' '.$id,
            'email' => $id.'@example.test',
            'password' => bcrypt('password'),
            'nim' => $role === 'mahasiswa' ? 'NIM'.$id : null,
            'nidn' => $role === 'dosen' ? 'NIDN'.$id : null,
            'registration_status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function program(User $student, string $jenis, array $roles, string $status = 'aktif'): MahasiswaTa
    {
        return MahasiswaTa::create(array_merge([
            'user_id' => $student->id,
            'jenis' => $jenis,
            'judul_ta' => 'Judul Direktori '.$student->id.' '.$jenis,
            'tempat_kp' => 'Tempat KP Direktori',
            'fase' => $jenis === 'ta' ? 'proposal' : 'laporan',
            'status_ta' => $status,
            'target_sesi' => 7,
        ], $roles));
    }

    public function test_directory_counts_unique_students_shows_roles_phase_and_aggregated_files(): void
    {
        $student = $this->newUser('mahasiswa', 'Directory Target');
        $ta = $this->program($student, 'ta', [
            'pembimbing_1_id' => $this->lecturer->id,
            'penguji_2_id' => $this->lecturer->id,
        ]);
        $kp = $this->program($student, 'kp', ['penguji_1_id' => $this->lecturer->id]);
        WorkspaceFile::create([
            'mahasiswa_ta_id' => $ta->id,
            'uploaded_by' => $student->id,
            'original_name' => 'draft.pdf',
            'path' => 'workspace/test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 123,
        ]);

        $response = $this->actingAs($this->lecturer)->get(route('workspace.role'));
        $response->assertOk()->assertSee('1 mahasiswa')
            ->assertSee('2 workspace')->assertSee('Pembimbing 1')->assertSee('Penguji 2')
            ->assertSee('Seminar Proposal')->assertSee('1 file')
            ->assertSee(route('workspace.index', $ta))->assertSee(route('workspace.index', $kp))
            ->assertViewHas('tas', fn ($tas) => $tas->total() === 2
                && $tas->firstWhere('id', $ta->id)->workspace_files_count === 1);
    }

    public function test_search_and_program_and_role_filters_are_combined_server_side(): void
    {
        $ta = $this->program($this->newUser('mahasiswa', 'Alpha Directory'), 'ta', ['pembimbing_2_id' => $this->lecturer->id]);
        $this->program($this->newUser('mahasiswa', 'Beta Directory'), 'kp', ['penguji_1_id' => $this->lecturer->id]);
        $query = ['student_search' => $ta->mahasiswa->nim, 'student_program' => 'ta', 'student_role' => 'pembimbing', 'student_view' => 'grid'];

        $this->actingAs($this->lecturer)->get(route('workspace.role', $query))
            ->assertOk()->assertViewHas('tas', fn ($tas) => $tas->total() === 1 && $tas->first()->id === $ta->id)
            ->assertSee('Pembimbing 2')->assertSee('Grid');
        $this->actingAs($this->lecturer)->get(route('workspace.role', ['student_search' => $ta->judul_ta, 'student_role' => 'penguji']))
            ->assertOk()->assertViewHas('tas', fn ($tas) => $tas->total() === 0)
            ->assertSee('Tidak ada workspace mahasiswa yang cocok');
        $this->actingAs($this->lecturer)->get(route('workspace.role', ['student_search' => 'Tempat KP Direktori', 'student_program' => 'kp']))
            ->assertOk()->assertViewHas('tas', fn ($tas) => $tas->total() === 1);
    }

    public function test_pending_rejected_and_unrelated_workspaces_never_appear_or_open(): void
    {
        $pending = $this->program($this->newUser('mahasiswa', 'Pending Directory'), 'ta', ['pembimbing_1_id' => $this->lecturer->id], 'pending_approval');
        $rejected = $this->program($this->newUser('mahasiswa', 'Rejected Directory'), 'ta', ['penguji_1_id' => $this->lecturer->id], 'ditolak');
        $unrelated = $this->program($this->newUser('mahasiswa', 'Unrelated Directory'), 'ta', []);

        $this->actingAs($this->lecturer)->get(route('workspace.role', ['student_search' => 'Directory']))
            ->assertOk()->assertSee('Belum ada workspace bimbingan yang tersedia.')
            ->assertDontSee(route('workspace.index', $pending))
            ->assertDontSee(route('workspace.index', $rejected))
            ->assertDontSee(route('workspace.index', $unrelated));
        $this->actingAs($this->lecturer)->get(route('workspace.index', $unrelated))->assertForbidden();
        $this->actingAs($this->lecturer)->get(route('workspace.index', $pending))->assertRedirect(route('approval.index'));
    }

    public function test_directory_paginates_and_preserves_filters(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->program($this->newUser('mahasiswa', 'Page Directory'), 'kp', ['pembimbing_1_id' => $this->lecturer->id]);
        }
        $this->actingAs($this->lecturer)->get(route('workspace.role', [
            'student_search' => 'Page Directory', 'student_program' => 'kp', 'student_page' => 2,
        ]))->assertOk()->assertViewHas('tas', fn ($tas) => $tas->total() === 21 && $tas->count() === 1 && $tas->currentPage() === 2)
            ->assertSee('21 mahasiswa')->assertSee('Menampilkan 21–21 dari 21 workspace')
            ->assertSee('student_program=kp', false);
    }

    public function test_file_counts_do_not_query_workspace_files_per_student(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->program($this->newUser('mahasiswa', 'Query Directory'), 'ta', ['pembimbing_1_id' => $this->lecturer->id]);
        }

        $fileQueries = 0;
        DB::listen(function ($query) use (&$fileQueries) {
            if (str_contains(strtolower($query->sql), 'workspace_files')) {
                $fileQueries++;
            }
        });

        $this->actingAs($this->lecturer)->get(route('workspace.role'))->assertOk();
        // One personal-files query and one correlated aggregate, not five counts.
        $this->assertLessThanOrEqual(3, $fileQueries);
    }
}
