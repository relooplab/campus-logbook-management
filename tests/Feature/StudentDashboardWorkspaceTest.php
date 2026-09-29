<?php

namespace Tests\Feature;

use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentDashboardWorkspaceTest extends TestCase
{
    use DatabaseTransactions;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'mahasiswa', 'guard_name' => 'web']);
        $id = uniqid();
        $this->student = User::create([
            'name' => 'Mahasiswa Dashboard '.$id,
            'email' => 'dashboard-'.$id.'@example.test',
            'password' => bcrypt('password'),
            'registration_status' => 'active',
            'nim' => 'NIM'.$id,
            'whatsapp' => '6281234567890',
        ]);
        $this->student->assignRole('mahasiswa');
    }

    private function program(string $jenis = MahasiswaTa::JENIS_TA, string $fase = 'proposal'): MahasiswaTa
    {
        return MahasiswaTa::create([
            'user_id' => $this->student->id,
            'jenis' => $jenis,
            'fase' => $fase,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
            'target_sesi' => 9,
        ]);
    }

    public function test_student_without_program_sees_profile_action_but_not_unavailable_creation_links(): void
    {
        $this->actingAs($this->student)->get(route('dashboard'))->assertOk()
            ->assertSee('Perlu Tindakan')
            ->assertSee('Lengkapi Profil Anda')
            ->assertSee(route('profile.index'))
            ->assertDontSee('href="'.route('logbook.create').'"', false)
            ->assertDontSee('Perjalanan TA');
    }

    public function test_ta_dashboard_has_ordered_sections_actual_target_and_semantic_health(): void
    {
        $ta = $this->program();
        $ta->update(['judul_ta' => 'Judul TA asli mahasiswa']);
        $response = $this->actingAs($this->student)->get(route('dashboard'));
        $response->assertOk()->assertSeeInOrder([
            'Perlu Tindakan', 'Perjalanan', 'Progres Bimbingan', 'Aktivitas Terbaru', 'Pencapaian', 'Statistik Ringkas', 'Aktivitas 12 Bulan',
        ])->assertSee('0 / 9 sesi disetujui')
            ->assertSee('Belum pernah bimbingan')
            ->assertSee('Judul TA asli mahasiswa')
            ->assertSee('Belum ada aktivitas terbaru.')
            ->assertSee(route('seminar-submission.create', $ta))
            ->assertDontSee('Timeline Bimbingan')
            ->assertDontSee('Status bimbingan Anda: Red');
    }

    public function test_blocking_title_and_entry_actions_use_real_counts_and_recent_activity_is_bounded(): void
    {
        $ta = $this->program();
        for ($i = 1; $i <= 6; $i++) {
            $entry = LogbookEntry::create([
                'mahasiswa_ta_id' => $ta->id,
                'jenis' => LogbookEntry::JENIS_LOGBOOK,
                'sesi_ke' => $i,
                'topik' => 'Dashboard topik '.$i,
                'status' => $i === 1 ? LogbookEntry::STATUS_DRAFT : LogbookEntry::STATUS_SUBMITTED,
            ]);
            $entry->forceFill(['created_at' => now()->subHours(6 - $i)])->save();
        }
        $response = $this->actingAs($this->student)->get(route('dashboard'));
        $response->assertOk()
            ->assertSee('Lengkapi Judul Tugas Akhir Anda')
            ->assertSee(route('logbook.index', ['status' => 'draft', 'program' => 'ta']))
            ->assertSee('Draf: 1')
            ->assertSee('Dashboard topik 6')
            ->assertDontSee('Dashboard topik 1');
    }

    public function test_kp_dashboard_uses_its_own_phase_and_links_without_ta_achievements(): void
    {
        $ta = $this->program(MahasiswaTa::JENIS_KP, 'laporan');
        $this->actingAs($this->student)->get(route('dashboard', ['program' => 'kp']))->assertOk()
            ->assertSee('Perjalanan')
            ->assertSee('Tempat Kerja Praktek')
            ->assertSee(route('logbook-harian.index', $ta))
            ->assertSee('Selesai KP? Lanjut ke Tugas Akhir')
            ->assertDontSee('id="achievements-heading"', false)
            ->assertDontSee('Kirim Bahan');
    }
}
