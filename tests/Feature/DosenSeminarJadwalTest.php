<?php

namespace Tests\Feature;

use App\Models\MahasiswaTa;
use App\Models\SeminarSubmission;
use App\Models\SeminarSubmissionRead;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Halaman "Agenda Seminar/Sidang" dosen: daftar bahan + jadwal terurut terdekat,
 * termasuk Seminar KP, badge "Baru"/belum dibaca, dan penandaan sudah dibaca.
 */
class DosenSeminarJadwalTest extends TestCase
{
    use DatabaseTransactions;

    private User $dosen;
    private MahasiswaTa $ta;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['mahasiswa', 'dosen'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $mk = function (array $a, string $r) {
            $u = User::create(array_merge(['password' => bcrypt('x'), 'registration_status' => 'active', 'whatsapp' => '628'], $a));
            $u->assignRole($r);

            return $u;
        };
        $this->dosen = $mk(['name' => 'Dsn', 'email' => 'dsn-'.uniqid().'@t.test', 'nidn' => 'NIDN-'.substr(md5(uniqid()), 0, 10)], 'dosen');
        $mhs = $mk(['name' => 'Mhs', 'email' => 'mhs-'.uniqid().'@t.test', 'nim' => 'NIM-'.uniqid()], 'mahasiswa');

        $this->ta = MahasiswaTa::create([
            'user_id' => $mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'judul_ta' => 'Judul',
            'pembimbing_1_id' => $this->dosen->id,
            'target_sesi' => 7,
            'fase' => 'proposal',
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
        ]);
    }

    private function makeSubmission(string $jenis, string $tanggal, string $waktu): SeminarSubmission
    {
        return SeminarSubmission::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'jenis' => $jenis,
            'tanggal' => $tanggal,
            'waktu' => $waktu,
            'undangan_path' => 'undangan-'.uniqid().'.pdf',
            'undangan_original_name' => 'undangan.pdf',
            'undangan_sebagai' => 'pembimbing_1',
            'status' => SeminarSubmission::STATUS_SUBMITTED,
        ]);
    }

    public function test_agenda_lists_upcoming_and_includes_seminar_kp(): void
    {
        $this->makeSubmission(SeminarSubmission::JENIS_PROPOSAL, now()->addDays(2)->toDateString(), '10:00');
        $this->makeSubmission(SeminarSubmission::JENIS_SEMINAR_KP, now()->addDays(1)->toDateString(), '14:30');

        $r = $this->actingAs($this->dosen)->get(route('dosen.seminar-jadwal'));
        $r->assertOk();
        $r->assertSee('Seminar KP');
        $r->assertSee('Seminar Proposal');
    }

    public function test_unread_badge_and_mark_read_on_view(): void
    {
        $sub = $this->makeSubmission(SeminarSubmission::JENIS_PROPOSAL, now()->addDays(2)->toDateString(), '10:00');

        // Belum dibaca -> tidak ada record read.
        $r = $this->actingAs($this->dosen)->get(route('dosen.seminar-jadwal'));
        $r->assertOk();
        $this->assertFalse($sub->isReadBy($this->dosen));

        // Aksen show -> tandai dibaca.
        $this->actingAs($this->dosen)->get(route('seminar-submission.show', $sub));
        $this->assertTrue($sub->fresh()->isReadBy($this->dosen));
        $this->assertNotNull(SeminarSubmissionRead::where('seminar_submission_id', $sub->id)->where('user_id', $this->dosen->id)->first());
    }

    public function test_past_tab_returns_past_submission(): void
    {
        $this->makeSubmission(SeminarSubmission::JENIS_PROPOSAL, now()->subDays(1)->toDateString(), '09:00');

        $r = $this->actingAs($this->dosen)->get(route('dosen.seminar-jadwal', ['tab' => 'past']));
        $r->assertOk();
        $r->assertSee('Seminar Proposal');
    }
}