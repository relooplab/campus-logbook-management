<?php

namespace Tests\Feature;

use App\Models\DosenChangeRequest;
use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Alur "Profil Akademik": mahasiswa mengusulkan penguji, disetujui SEMUA
 * dosen terkait (pembimbing + penguji + calon), diterapkan setelah semua
 * approve; bisa mengajukan ulang setelah reject.
 */
class ProfilAkademikTest extends TestCase
{
    use DatabaseTransactions;

    private User $mhs;
    private User $pembimbing;
    private User $pengujiLama;
    private User $pengujiBaru;
    private MahasiswaTa $ta;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['mahasiswa', 'dosen'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $make = function (array $attr, string $role) {
            $u = User::create(array_merge([
                'password' => bcrypt('x'), 'registration_status' => 'active', 'whatsapp' => '628',
            ], $attr));
            $u->assignRole($role);

            return $u;
        };

        $this->mhs = $make(['name' => 'Mhs', 'email' => 'mhs-'.uniqid().'@t.test', 'nim' => 'NIM-'.uniqid()], 'mahasiswa');
        $this->pembimbing = $make(['name' => 'Pemb', 'email' => 'pemb-'.uniqid().'@t.test', 'nidn' => 'NIDN-'.substr(md5(uniqid()), 0, 12)], 'dosen');
        $this->pengujiLama = $make(['name' => 'PengLama', 'email' => 'pengL-'.uniqid().'@t.test', 'nidn' => 'NIDN-'.substr(md5(uniqid()), 0, 12)], 'dosen');
        $this->pengujiBaru = $make(['name' => 'PengBaru', 'email' => 'pengB-'.uniqid().'@t.test', 'nidn' => 'NIDN-'.substr(md5(uniqid()), 0, 12)], 'dosen');

        $this->ta = MahasiswaTa::create([
            'user_id' => $this->mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'judul_ta' => 'Judul TA Test',
            'pembimbing_1_id' => $this->pembimbing->id,
            'penguji_1_id' => $this->pengujiLama->id,
            'target_sesi' => 7,
            'fase' => 'proposal',
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
        ]);
    }

    public function test_mahasiswa_can_propose_penguji_and_all_must_approve(): void
    {
        $this->actingAs($this->mhs)
            ->post(route('profile.profil-akademik.penguji'), [
                'mahasiswa_ta_id' => $this->ta->id,
                'proposed_dosen_id' => $this->pengujiBaru->id,
            ])->assertRedirect();

        $change = DosenChangeRequest::where('mahasiswa_ta_id', $this->ta->id)->first();
        $this->assertNotNull($change);
        $this->assertSame('pending', $change->status);
        $this->assertSame('penguji_2', $change->proposed_role);

        // Approver = pembimbing + penguji lama + calon baru.
        $this->assertSame(
            [$this->pembimbing->id, $this->pengujiLama->id, $this->pengujiBaru->id],
            $change->requiredApproverIds()
        );

        // Belum semua approve -> belum diterapkan.
        $this->actingAs($this->pembimbing)->post(route('approval.penguji.approve', $change))->assertRedirect();
        $this->assertNull($this->ta->fresh()->penguji_2_id);

        $this->actingAs($this->pengujiLama)->post(route('approval.penguji.approve', $change))->assertRedirect();
        $this->assertNull($this->ta->fresh()->penguji_2_id);

        // Semua approve -> diterapkan.
        $this->actingAs($this->pengujiBaru)->post(route('approval.penguji.approve', $change))->assertRedirect();
        $this->assertSame($this->pengujiBaru->id, $this->ta->fresh()->penguji_2_id);
        $this->assertSame('approved', $change->fresh()->status);
    }

    public function test_reject_blocks_and_allows_resubmit(): void
    {
        $this->actingAs($this->mhs)
            ->post(route('profile.profil-akademik.penguji'), [
                'mahasiswa_ta_id' => $this->ta->id,
                'proposed_dosen_id' => $this->pengujiBaru->id,
            ])->assertRedirect();

        $change = DosenChangeRequest::where('mahasiswa_ta_id', $this->ta->id)->first();

        // Satu reject -> seluruh request ditolak & tidak diterapkan.
        $this->actingAs($this->pengujiLama)
            ->post(route('approval.penguji.reject', $change), ['alasan_tolak' => 'Tidak setuju'])
            ->assertRedirect();

        $this->assertSame('rejected', $change->fresh()->status);
        $this->assertNull($this->ta->fresh()->penguji_2_id);

        // Mahasiswa bisa mengajukan ulang (rejected tidak menghalangi).
        $this->actingAs($this->mhs)
            ->post(route('profile.profil-akademik.penguji'), [
                'mahasiswa_ta_id' => $this->ta->id,
                'proposed_dosen_id' => $this->pengujiBaru->id,
            ])->assertRedirect();

        $this->assertSame(2, DosenChangeRequest::where('mahasiswa_ta_id', $this->ta->id)->count());
    }

    public function test_cannot_propose_if_already_pending_or_same_dosen(): void
    {
        $this->actingAs($this->mhs)
            ->post(route('profile.profil-akademik.penguji'), [
                'mahasiswa_ta_id' => $this->ta->id,
                'proposed_dosen_id' => $this->pengujiBaru->id,
            ])->assertRedirect();

        // Dosen yang sudah terlibat (penguji lama) tidak boleh diusulkan.
        $this->actingAs($this->mhs)
            ->post(route('profile.profil-akademik.penguji'), [
                'mahasiswa_ta_id' => $this->ta->id,
                'proposed_dosen_id' => $this->pengujiLama->id,
            ])->assertSessionHas('error');

        // Masih ada pending -> usulan lain ditolak.
        $this->actingAs($this->mhs)
            ->post(route('profile.profil-akademik.penguji'), [
                'mahasiswa_ta_id' => $this->ta->id,
                'proposed_dosen_id' => $this->pembimbing->id,
            ])->assertSessionHas('error');
    }
}