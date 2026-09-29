<?php

namespace Tests\Feature;

use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\SeminarSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LecturerDashboardOverviewTest extends TestCase
{
    use DatabaseTransactions;

    private User $lecturer;

    private MahasiswaTa $program;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['mahasiswa', 'dosen'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->lecturer = $this->user('dosen');
        $student = $this->user('mahasiswa');
        $this->program = MahasiswaTa::create([
            'user_id' => $student->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->lecturer->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
            'fase' => 'proposal',
        ]);
    }

    private function user(string $role): User
    {
        $id = uniqid();
        $user = User::create([
            'name' => 'Dashboard '.$id,
            'email' => $id.'@t.test',
            'password' => bcrypt('password'),
            'registration_status' => 'active',
            'nim' => $role === 'mahasiswa' ? 'NIM'.$id : null,
            'nidn' => $role === 'dosen' ? 'NIDN'.$id : null,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_overview_has_workflow_links_and_no_expanded_tables(): void
    {
        LogbookEntry::create([
            'mahasiswa_ta_id' => $this->program->id,
            'dosen_id' => $this->lecturer->id,
            'jenis' => LogbookEntry::JENIS_LOGBOOK,
            'sesi_ke' => 1,
            'topik' => 'Bimbingan terbaru',
            'tanggal_bimbingan' => now()->toDateString(),
            'status' => LogbookEntry::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($this->lecturer)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Antrean Review')
            ->assertSee('Mahasiswa Prioritas')
            ->assertSee('Distribusi Fase')
            ->assertSee('Tidak ada mahasiswa yang memerlukan perhatian khusus.')
            ->assertSee(route('dashboard.dosen.mahasiswa-list'))
            ->assertSee(route('dosen.mahasiswa-saya'))
            ->assertSee(route('dosen.seminar-jadwal'))
            ->assertDontSee('Manajemen Fase Mahasiswa (')
            ->assertDontSee('Health Indicator Bimbingan');
    }

    public function test_review_and_agenda_are_limited_and_priority_uses_existing_health(): void
    {
        // A program with no dated guidance is critical according to the existing regularity model.
        for ($i = 1; $i <= 7; $i++) {
            LogbookEntry::create([
                'mahasiswa_ta_id' => $this->program->id,
                'dosen_id' => $this->lecturer->id,
                'jenis' => LogbookEntry::JENIS_LOGBOOK,
                'sesi_ke' => $i,
                'topik' => 'Topik-'.$i,
                'status' => LogbookEntry::STATUS_SUBMITTED,
                'submitted_at' => now()->subMinutes($i),
            ]);
        }

        for ($i = 1; $i <= 6; $i++) {
            SeminarSubmission::create([
                'mahasiswa_ta_id' => $this->program->id,
                'jenis' => SeminarSubmission::JENIS_PROPOSAL,
                'tanggal' => now()->addDays($i)->toDateString(),
                'waktu' => '09:00',
                'undangan_path' => 'test.pdf',
                'undangan_original_name' => 'test.pdf',
                'undangan_kepada' => ['pembimbing_1'],
                'status' => SeminarSubmission::STATUS_SUBMITTED,
            ]);
        }

        $response = $this->actingAs($this->lecturer)->get(route('dashboard'));
        $response->assertOk()
            ->assertSee('13 bahan menunggu review')
            ->assertSee('Topik-1')
            ->assertSee('Topik-5')
            ->assertDontSee('Topik-6')
            ->assertSee('Kritis')
            ->assertSee(route('mahasiswa-ta.show', $this->program));

        $this->assertCount(5, $response->viewData('queue'));
        $this->assertCount(4, $response->viewData('agendaTerdekat'));
        $this->assertSame(7, $response->viewData('queueCount'));
        $this->assertSame(1, $response->viewData('phaseDistribution')->first()['count']);
    }
}
