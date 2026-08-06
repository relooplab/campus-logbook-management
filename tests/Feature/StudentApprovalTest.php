<?php

namespace Tests\Feature;

use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class StudentApprovalTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    public function test_dosen_can_invite_mahasiswa_by_email_only(): void
    {
        $response = $this->actingAs($this->dosen)->post(route('approval.invite'), [
            'email' => 'budiman@mail.com',
        ]);

        $response->assertRedirect(route('approval.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'budiman@mail.com',
            'registration_status' => 'active',
        ]);

        $user = User::where('email', 'budiman@mail.com')->first();
        $this->assertTrue($user->hasRole('mahasiswa'));
        $this->assertEquals('Budiman', $user->name);
    }

    public function test_invite_rejects_duplicate_email(): void
    {
        User::create([
            'name' => 'Existing',
            'email' => 'existing@mail.com',
            'password' => bcrypt('password'),
            'registration_status' => 'approved',
        ]);

        $this->actingAs($this->dosen)
            ->post(route('approval.invite'), ['email' => 'existing@mail.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_dosen_can_approve_invited_mahasiswa_and_assign_role(): void
    {
        // Buat mahasiswa aktif (email verified, belum attach dosen).
        $mhs = User::create([
            'name' => 'Budiman',
            'email' => 'budiman2@mail.com',
            'password' => bcrypt('password'),
            'registration_status' => 'active',
        ]);
        $mhs->syncRoles(['mahasiswa']);

        // Buat MahasiswaTa dengan status pending_approval (mahasiswa memilih dosen ini).
        $ta = MahasiswaTa::create([
            'user_id' => $mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosen->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_PENDING_APPROVAL,
            'fase' => 'proposal',
        ]);

        $this->actingAs($this->dosen)->post(route('approval.approve', $ta), [
            'judul_ta' => 'Sistem Informasi',
            'role_dosen' => 'pembimbing_1',
            'target_sesi' => 7,
        ])->assertRedirect(route('approval.index'));

        // Mahasiswa jadi verified.
        $this->assertDatabaseHas('users', [
            'id' => $mhs->id,
            'registration_status' => 'verified',
        ]);

        // MahasiswaTa jadi aktif.
        $this->assertDatabaseHas('mahasiswa_ta', [
            'id' => $ta->id,
            'user_id' => $mhs->id,
            'pembimbing_1_id' => $this->dosen->id,
            'judul_ta' => 'Sistem Informasi',
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
        ]);
    }

    public function test_reject_tanpa_alasan_gagal(): void
    {
        $mhs = User::create([
            'name' => 'Budiman',
            'email' => 'budiman-reject@mail.com',
            'password' => bcrypt('password'),
            'registration_status' => 'active',
        ]);
        $mhs->syncRoles(['mahasiswa']);

        $ta = MahasiswaTa::create([
            'user_id' => $mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosen->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_PENDING_APPROVAL,
            'fase' => 'proposal',
        ]);

        $this->actingAs($this->dosen)
            ->post(route('approval.reject', $ta), [])
            ->assertSessionHasErrors('alasan_ditolak');

        // Status tetap pending — tidak berubah jadi ditolak.
        $this->assertDatabaseHas('mahasiswa_ta', [
            'id' => $ta->id,
            'status_ta' => MahasiswaTa::STATUS_PENDING_APPROVAL,
        ]);
    }

    public function test_reject_dengan_alasan_berhasil_dan_tersimpan(): void
    {
        $mhs = User::create([
            'name' => 'Budiman',
            'email' => 'budiman-reject2@mail.com',
            'password' => bcrypt('password'),
            'registration_status' => 'active',
        ]);
        $mhs->syncRoles(['mahasiswa']);

        $ta = MahasiswaTa::create([
            'user_id' => $mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosen->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_PENDING_APPROVAL,
            'fase' => 'proposal',
        ]);

        $this->actingAs($this->dosen)
            ->post(route('approval.reject', $ta), [
                'alasan_ditolak' => 'Kuota bimbingan sudah penuh',
            ])
            ->assertRedirect(route('approval.index'));

        $this->assertDatabaseHas('mahasiswa_ta', [
            'id' => $ta->id,
            'status_ta' => MahasiswaTa::STATUS_DITOLAK,
            'alasan_ditolak' => 'Kuota bimbingan sudah penuh',
        ]);
    }

    public function test_dashboard_mahasiswa_menampilkan_alasan_penolakan(): void
    {
        $mhs = User::create([
            'name' => 'Budiman',
            'email' => 'budiman-reject3@mail.com',
            'password' => bcrypt('password'),
            'registration_status' => 'active',
        ]);
        $mhs->syncRoles(['mahasiswa']);

        $ta = MahasiswaTa::create([
            'user_id' => $mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosen->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_DITOLAK,
            'fase' => 'proposal',
            'alasan_ditolak' => 'Bukan bidang saya',
        ]);

        $response = $this->actingAs($mhs)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Bukan bidang saya');
    }
}
