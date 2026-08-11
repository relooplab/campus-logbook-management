<?php

namespace Tests\Feature;

use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regresi: saat dosen menyetujui program mahasiswa, mahasiswa mendapat
 * notifikasi (dan program jadi aktif / mahasiswa verified).
 */
class StudentApprovalNotificationTest extends TestCase
{
    use DatabaseTransactions;

    private User $mhs;
    private User $dosen;
    private MahasiswaTa $ta;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['mahasiswa', 'dosen'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->mhs = User::create([
            'name' => 'Mhs Approve',
            'email' => 'mhs-approve-'.uniqid().'@t.test',
            'password' => bcrypt('password'),
            'nim' => 'NIM'.substr(md5(uniqid()), 0, 8),
            'whatsapp' => '6281234567890',
            'registration_status' => 'active',
        ]);
        $this->mhs->assignRole('mahasiswa');

        $this->dosen = User::create([
            'name' => 'Dosen Approver',
            'email' => 'dosen-approve-'.uniqid().'@t.test',
            'password' => bcrypt('password'),
            'nidn' => 'NIDN'.substr(md5(uniqid()), 0, 10),
            'registration_status' => 'active',
        ]);
        $this->dosen->assignRole('dosen');

        $this->ta = MahasiswaTa::create([
            'user_id' => $this->mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosen->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_PENDING_APPROVAL,
            'fase' => 'proposal',
        ]);
    }

    public function test_approve_menghubungi_mahasiswa(): void
    {
        $this->actingAs($this->dosen)
            ->post(route('approval.approve', $this->ta), [
                'judul_ta' => 'Judul Skripsi',
                'role_dosen' => 'pembimbing_1',
                'target_sesi' => 7,
                'fase' => 'proposal',
            ])->assertRedirect(route('approval.index'));

        // Program aktif + mahasiswa verified.
        $this->assertSame(MahasiswaTa::STATUS_AKTIF, $this->ta->fresh()->status_ta);
        $this->assertSame('verified', $this->mhs->fresh()->registration_status);

        // Mahasiswa dapat notifikasi "Program Disetujui".
        $this->assertSame(1, $this->mhs->fresh()->notifications()->count());
        $data = $this->mhs->fresh()->notifications()->first()->data;
        $this->assertStringContainsString('telah disetujui', $data['message']);
    }
}
