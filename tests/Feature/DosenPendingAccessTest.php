<?php

namespace Tests\Feature;

use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\SeminarSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Dosen tidak boleh mengakses materi/workspace mahasiswa yang programnya
 * masih MENUNGGU persetujuan — diarahkan ke halaman persetujuan (bukan 403).
 */
class DosenPendingAccessTest extends TestCase
{
    use DatabaseTransactions;

    private User $dosen;
    private User $mhs;
    private MahasiswaTa $ta;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['mahasiswa', 'dosen'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->dosen = User::create([
            'name' => 'Dosen Pending', 'email' => 'dosen-pending-'.uniqid().'@t.test',
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nidn' => 'NIDN'.substr(md5(uniqid()), 0, 10),
        ]);
        $this->dosen->assignRole('dosen');

        $this->mhs = User::create([
            'name' => 'Mhs Pending', 'email' => 'mhs-pending-'.uniqid().'@t.test',
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => 'NIM'.substr(md5(uniqid()), 0, 8),
            'whatsapp' => '6281234567890',
        ]);
        $this->mhs->assignRole('mahasiswa');

        $this->ta = MahasiswaTa::create([
            'user_id' => $this->mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosen->id,
            'status_ta' => MahasiswaTa::STATUS_PENDING_APPROVAL,
        ]);
    }

    public function test_dosen_di_redirect_ke_approval_saat_buka_workspace_pending(): void
    {
        $this->actingAs($this->dosen)
            ->get(route('workspace.index', $this->ta))
            ->assertRedirect(route('approval.index'));
    }

    public function test_dosen_di_redirect_ke_approval_saat_buka_matari_seminar_pending(): void
    {
        $submission = SeminarSubmission::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'jenis' => SeminarSubmission::JENIS_PROPOSAL,
            'status' => SeminarSubmission::STATUS_SUBMITTED,
            'tanggal' => now()->addDays(5),
            'waktu' => '10:00',
            'lokasi' => 'Ruang A',
            'undangan_path' => 'seminar-materials/undangan.pdf',
            'undangan_original_name' => 'undangan.pdf',
            'undangan_kepada' => ['pembimbing_1'],
        ]);

        $this->actingAs($this->dosen)
            ->get(route('seminar-submission.show', $submission))
            ->assertRedirect(route('approval.index'));
    }

    public function test_mahasiswa_dapat_akses_materi_nya_saat_pending(): void
    {
        $this->actingAs($this->mhs)
            ->get(route('workspace.index', $this->ta))
            ->assertOk();
    }

    public function test_dosen_dapat_akses_setelah_program_disetujui(): void
    {
        $this->ta->update(['status_ta' => MahasiswaTa::STATUS_AKTIF]);

        $this->actingAs($this->dosen)
            ->get(route('workspace.index', $this->ta))
            ->assertOk();
    }

    public function test_antrean_review_tidak_memuat_mahasiswa_pending(): void
    {
        LogbookEntry::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'jenis' => LogbookEntry::JENIS_LOGBOOK,
            'sesi_ke' => 1,
            'dosen_id' => $this->dosen->id,
            'topik' => 'Bab 1',
            'status' => LogbookEntry::STATUS_SUBMITTED,
        ]);

        $queue = app(\App\Services\MaterialsReviewQueue::class)->countFor($this->dosen);

        $this->assertSame(0, $queue, 'Mahasiswa pending tidak boleh masuk antrean review dosen.');
    }
}